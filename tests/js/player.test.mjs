import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {grade, summarize} from '../../public/assets/js/player-scoring.js';
import {createState, editAnswer, submitAnswer, nextQuestion, finishTimed, deadlines, syncPayload, mergeServer, hasPending, PlayerClock} from '../../public/assets/js/player-state.js';
import {PlayerSync} from '../../public/assets/js/player-sync.js';

const cases = JSON.parse(fs.readFileSync(new URL('../fixtures/player-scoring.json', import.meta.url), 'utf8'));
for (const fixture of cases) test(fixture.name, () => assert.deepEqual(grade(fixture.question, fixture.answer), fixture.expected));
const startedAt = '2026-09-26T10:00:00.000000Z';
const data = () => ({mode: 'assessment', attemptId: 'attempt', credential: 'test-only', version: 1, startedAt, totalDueAt: '2026-09-26T10:02:00Z', closeAt: null,
  quiz: {shareToken: 'quiz', title: 'Quiz', settings: {feedback: 'after_each'}, questions: [{...cases[0].question, timeLimitSec: 30}, {...cases[0].question, id: 'second', timeLimitSec: 15}]}});
const fresh = () => createState(data(), startedAt, Date.parse(startedAt));
const clone = value => JSON.parse(JSON.stringify(value));

test('submissions lock immediately offline and next starts a fresh question timer', () => {
  const state = fresh();
  editAnswer(state, {answerCodes: ['right']});
  assert.equal(submitAnswer(state, 'answered', 'a'.repeat(32)), true);
  assert.equal(editAnswer(state, {answerCodes: ['wrong']}), false);
  assert.equal(deadlines(state).length, 1);
  assert.equal(nextQuestion(state, '2026-09-26T10:00:10Z'), true);
  assert.equal(state.index, 1);
  assert.equal(deadlines(state)[0].at, Date.parse('2026-09-26T10:00:25Z'));
  assert.equal(nextQuestion(state, startedAt), false);
  assert.equal(hasPending(state), true);
});

test('total timeout keeps current draft and leaves later answers unanswered', () => {
  const state = fresh();
  editAnswer(state, {answerCodes: ['right']});
  finishTimed(state, 'total_timeout', 'a'.repeat(32));
  assert.equal(state.finishReason, 'total_timeout');
  assert.equal(state.items[0].reason, 'attempt_timeout');
  assert.equal(summarize(state.quiz.questions, state.items).score, '2.50');
  assert.equal(summarize(state.quiz.questions, state.items).items[1].result, 'unanswered');
});

test('question timeout evaluates the draft, while explicit skip clears it', () => {
  const state = fresh(); editAnswer(state, {answerCodes: ['right']});
  submitAnswer(state, 'question_timeout', 'a'.repeat(32));
  assert.equal(grade(state.quiz.questions[0], state.items[0]).result, 'correct');
  assert.equal(state.items[0].reason, 'question_timeout');
  nextQuestion(state, '2026-09-26T10:00:31Z');
  editAnswer(state, {answerCodes: ['right']}); submitAnswer(state, 'skipped', 'b'.repeat(32));
  assert.equal(grade(state.quiz.questions[1], state.items[1]).result, 'unanswered');
});

test('captured schedule continues through feedback and overall deadlines win ties', () => {
  const state = fresh(); state.closeAt = '2026-09-26T10:00:30Z';
  assert.equal(deadlines(state)[0].reason, 'scheduled_close');
  submitAnswer(state, 'skipped', 'a'.repeat(32));
  assert.equal(deadlines(state)[0].reason, 'scheduled_close');
  finishTimed(state, 'scheduled_close');
  assert.equal(state.finishReason, 'scheduled_close');
  assert.equal(state.items[1].status, 'pending');
  assert.equal(nextQuestion(state, '2026-09-26T10:00:31Z'), false);
});

test('later local edits survive acknowledgements and older responses cannot roll back versions', () => {
  const state = fresh(); editAnswer(state, {answerCodes: ['right']});
  const remoteItem = {...clone(state.items[0]), status: 'active'};
  editAnswer(state, {answerCodes: ['wrong']});
  assert.equal(mergeServer(state, {version: 2, items: [remoteItem], result: null}), true);
  assert.deepEqual(state.items[0].answerCodes, ['wrong']);
  assert.equal(state.items[0].ackVer, 1);
  mergeServer(state, {version: 1, items: [], result: null});
  assert.equal(state.version, 2);
});

test('conflicting immutable server answer preserves the local draft', () => {
  const state = fresh(); editAnswer(state, {answerCodes: ['right']}); submitAnswer(state, 'answered', 'a'.repeat(32));
  const remote = {...clone(state.items[0]), answerCodes: ['wrong'], submitKey: 'b'.repeat(32)};
  assert.equal(mergeServer(state, {version: 2, items: [remote], result: null}), false);
  assert.deepEqual(state.items[0].answerCodes, ['right']);
});

test('sync queue serializes requests and catches edits made during an in-flight save', async () => {
  const state = fresh(); editAnswer(state, {answerCodes: ['right']});
  let release, calls = 0, inflight = 0, maxInflight = 0;
  const send = async (_path, {body}) => {
    calls++; inflight++; maxInflight = Math.max(maxInflight, inflight);
    const captured = clone(body);
    if (calls === 1) await new Promise(resolve => { release = resolve; });
    inflight--;
    return {data: {version: calls + 1, result: null, items: captured.items.map(item => ({...item, status: item.submitKey ? 'locked' : 'active'}))}};
  };
  const worker = new PlayerSync(() => state, send);
  const first = worker.flush();
  editAnswer(state, {answerCodes: ['wrong']});
  await worker.flush();
  assert.equal(calls, 1);
  release(); await first;
  assert.equal(calls, 2); assert.equal(maxInflight, 1); assert.equal(hasPending(state), false);
  assert.deepEqual(state.items[0].answerCodes, ['wrong']); worker.stop();
});

test('network failures keep the outbox for explicit retry', async () => {
  const state = fresh(); editAnswer(state, {answerCodes: ['right']});
  let online = false;
  const worker = new PlayerSync(() => state, async (_path, {body}) => {
    if (!online) throw new Error('offline');
    return {data: {version: 2, result: null, items: body.items.map(item => ({...item, status: 'active'}))}};
  });
  await worker.flush(); clearTimeout(worker.timer);
  assert.equal(hasPending(state), true); online = true;
  await worker.flush(); assert.equal(hasPending(state), false); worker.stop();
});

test('practice never sends an answer or result request', async () => {
  const state = fresh(); state.mode = 'practice'; editAnswer(state, {answerCodes: ['right']});
  const worker = new PlayerSync(() => state, async () => { assert.fail('Practice sent answers'); });
  await worker.flush(); assert.equal(hasPending(state), false); worker.stop();
});

test('clock survives backgrounding, reload and backwards wall-clock changes', () => {
  const state = fresh(); let wall = Date.parse(startedAt), monotonic = 0;
  const clock = new PlayerClock(state, () => monotonic, () => wall);
  monotonic += 10000; wall -= 10000;
  assert.equal(clock.now(), Date.parse(startedAt) + 10000);
  wall = Date.parse(startedAt) + 60000;
  assert.equal(clock.now(), wall);
  const reloaded = new PlayerClock(clone(state), () => 0, () => wall + 5000);
  assert.equal(reloaded.now(), wall + 5000);
});

test('sync batches at most 100 items and defers finish until the last batch', () => {
  const state = fresh(); const item = state.items[0];
  state.items = Array.from({length: 101}, (_, index) => ({...clone(item), questionId: String(index), saveVer: 1})); state.finishReason = 'completed';
  assert.equal(syncPayload(state).items.length, 100);
  assert.equal(syncPayload(state).finishReason, null);
});
