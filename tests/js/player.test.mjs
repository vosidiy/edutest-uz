import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {grade, summarize} from '../../public/js/player-scoring.js';
import {
  createState, editAnswer, submitAnswer, nextQuestion, finishTimed, quitQuiz,
  deadlines, mergeServer, hasPending, PlayerClock, receiptOnly,
} from '../../public/js/player-state.js';
import {PlayerSync} from '../../public/js/player-sync.js';

const cases = JSON.parse(fs.readFileSync(new URL('../fixtures/player-scoring.json', import.meta.url), 'utf8'));
const startedAt = '2026-09-26T10:00:00.000Z';
const expiresAt = '2026-09-26T12:00:00.000Z';
const questions = [
  {...cases[0].question, content:'First?', optionsLayout:'list', explanation:'First explanation', media:null,
    options: cases[0].question.options.map((option, index) => ({...option, id:String(index + 1), content:option.code, media:null}))},
  {...cases[0].question, id:'second', content:'Second?', optionsLayout:'grid', explanation:'Second explanation', media:null,
    options: cases[0].question.options.map((option, index) => ({...option, id:String(index + 3), content:option.code, media:null}))},
];
const snapshot = () => ({
  schemaVersion:1,
  quiz:{title:'Quiz',description:'Description',shareToken:'123456789',timeLimitSec:7200,askEmail:false,askPhone:false,
    feedback:'after_each',showScore:true,showAnswers:true,showExplain:true},
  cover:null,
  questions,
});
const data = () => ({attemptId:'attempt',credential:'secret',status:'in_progress',answerVersion:0,answers:[],
  startedAt,expiresAt,finishedAt:null,finishReason:null,lateSync:false,student:{name:'Alex'},quiz:snapshot(),result:null});
const fresh = () => createState(data());
const clone = value => JSON.parse(JSON.stringify(value));
const answer = (state, code = 'right', at = Date.parse(startedAt)) => {
  assert.equal(editAnswer(state, code, at), true);
  assert.equal(submitAnswer(state, false, at), true);
};
const complete = state => {
  answer(state, 'right', Date.parse(startedAt));
  nextQuestion(state, Date.parse(startedAt) + 1000);
  answer(state, 'right', Date.parse(startedAt) + 2000);
  nextQuestion(state, Date.parse(startedAt) + 3000);
};
const terminal = state => ({
  attemptId:state.attemptId,status:'completed',answerVersion:state.answerVersion,answers:clone(state.answers),
  startedAt:state.startedAt,expiresAt:state.expiresAt,finishedAt:state.finishRecord.clientFinishedAt,
  finishReason:state.finishReason,lateSync:false,student:state.student,quiz:snapshot(),
  result:{official:true,score:2,maxScore:2,percent:'100.00',items:[]},
});

for (const fixture of cases) {
  test(fixture.name, () => assert.deepEqual(grade(fixture.question, fixture.answer), fixture.expected));
}

test('confirmed answers form an ordered versioned prefix', () => {
  const state = fresh();
  answer(state);
  assert.equal(state.answerVersion, 1);
  assert.equal(state.progressPending, true);
  assert.deepEqual(state.answers[0], {questionId:questions[0].id,answerCode:'right',answeredAt:startedAt});
  assert.equal(nextQuestion(state, Date.parse(startedAt) + 1000), true);
  assert.equal(state.index, 1);
  assert.equal(state.items[0].status, 'locked');
});

test('skip is explicit and final completion includes every question', () => {
  const state = fresh();
  assert.equal(submitAnswer(state, true, Date.parse(startedAt)), true);
  assert.equal(state.answers[0].answerCode, null);
  assert.equal(state.answers[0].answeredAt, startedAt);
  nextQuestion(state, Date.parse(startedAt) + 1000);
  answer(state, 'right', Date.parse(startedAt) + 2000);
  nextQuestion(state, Date.parse(startedAt) + 3000);
  assert.equal(state.finishReason, 'completed');
  assert.equal(state.finishRecord.answers.length, 2);
  assert.deepEqual(summarize(state.quiz.questions, state.answers).items.map(item => item.result), ['skipped','correct']);
});

test('expiry and intentional exit fill the unanswered tail without resetting the deadline', () => {
  const expired = fresh();
  answer(expired);
  assert.equal(finishTimed(expired), true);
  assert.equal(expired.finishReason, 'expired');
  assert.equal(expired.finishRecord.clientFinishedAt, expiresAt);
  assert.deepEqual(expired.answers[1], {questionId:'second',answerCode:null,answeredAt:null});
  assert.deepEqual(deadlines(expired), [{at:Date.parse(expiresAt),reason:'expired',label:'quizTimer'}]);

  const exited = fresh();
  assert.equal(quitQuiz(exited, Date.parse(startedAt) + 5000), true);
  assert.equal(exited.finishReason, 'exited');
  assert.equal(exited.answers.length, 2);
});

test('a loaded attempt remains usable offline and terminal server data replaces local provisional state', () => {
  const state = fresh();
  complete(state);
  assert.equal(hasPending(state), true);
  assert.equal(mergeServer(state, terminal(state)), true);
  assert.equal(state.status, 'completed');
  assert.equal(state.result.official, true);
  assert.equal(hasPending(state), false);
});

test('Finish sends the complete latest document and overrides queued progress', async () => {
  const state = fresh();
  complete(state);
  const requests = [];
  const worker = new PlayerSync(() => state, async (path, request) => {
    requests.push({path,request});
    return {data:terminal(state)};
  });
  try {
    await worker.flush();
    assert.equal(requests.length, 1);
    assert.ok(requests[0].path.endsWith('/finish'));
    assert.equal(requests[0].request.body.answers.length, 2);
    assert.equal(hasPending(state), false);
  } finally { worker.stop(); }
});

test('failed progress remains queued and a retry preserves its timestamp', async () => {
  const state = fresh();
  answer(state);
  const before = clone(state.answers);
  let fail = true;
  const worker = new PlayerSync(() => state, async () => {
    if (fail) { fail = false; throw new Error('offline'); }
    return {data:{attemptId:state.attemptId,status:'in_progress',answerVersion:state.answerVersion}};
  });
  try {
    await worker.flush();
    assert.equal(state.progressPending, true);
    assert.deepEqual(state.answers, before);
    await worker.flush();
    assert.equal(state.progressPending, false);
    assert.deepEqual(state.answers, before);
  } finally { worker.stop(); }
});

test('server recovery detects a changed same-version document', () => {
  const state = fresh();
  answer(state);
  const conflicting = {...data(),answerVersion:1,answers:[{...state.answers[0],answerCode:'wrong'}]};
  const before = clone(state);
  assert.equal(mergeServer(state, conflicting), false);
  assert.deepEqual(state, before);
});

test('clock resists a backwards wall-clock change', () => {
  const state = fresh();
  let wall = Date.parse(startedAt), monotonic = 0;
  state.lastClock = wall;
  const clock = new PlayerClock(state, () => monotonic, () => wall);
  monotonic += 10000;
  wall -= 10000;
  assert.equal(clock.now(), Date.parse(startedAt) + 10000);
});

test('receipt-only mode requires every result visibility flag to be off', () => {
  for (const showScore of [true,false]) for (const showAnswers of [true,false]) for (const showExplain of [true,false]) {
    assert.equal(receiptOnly({showScore,showAnswers,showExplain}), !(showScore || showAnswers || showExplain));
  }
});
