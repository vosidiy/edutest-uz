import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {grade, summarize} from '../../public/js/player-scoring.js';
import {createState, editAnswer, submitAnswer, nextQuestion, finishTimed, quitQuiz, deadlines, visibleDeadlines, mergeServer, hasPending, hasUploadPending, PlayerClock, isFullscreenExit, acknowledge, recordActivity, receiptOnly} from '../../public/js/player-state.js';
import {PlayerSync} from '../../public/js/player-sync.js';

const cases = JSON.parse(fs.readFileSync(new URL('../fixtures/player-scoring.json', import.meta.url), 'utf8'));
const startedAt = '2026-09-26T10:00:00.000000Z', expiresAt = '2026-09-26T10:02:00.000000Z';
const questions = [cases[0].question, {...cases[0].question, id:'second'}];
const data = () => ({mode:'assessment', attemptId:'attempt', credential:'secret', status:'in_progress', startedAt, expiresAt,
  clientActivityAt:startedAt, deadlineReason:'timer_expired', student:{name:'Alex Morgan',email:'alex@example.test'}, quiz:{shareToken:'quiz',title:'Quiz',settings:{feedback:'after_each',timeLimitSec:120,closesAt:null},questions}});
const fresh = () => createState(data(), startedAt, Date.parse(startedAt));
const clone = value => JSON.parse(JSON.stringify(value));
const server = state => ({...data(), items:clone(state.items), result:null, finishReason:null});
const ack = (state, operation) => ({attemptId:state.attemptId,status:'in_progress',expiresAt:state.expiresAt,deadlineReason:state.deadlineReason,
  clientActivityAt:operation?.clientActivityAt || state.clientActivityAt, ...(operation ? {questionId:operation.questionId,answerStatus:operation.status}: {})});
const submit = state => { editAnswer(state,{answerCodes:['right']}); submitAnswer(state); };
const complete = state => { submit(state); nextQuestion(state); submit(state); nextQuestion(state); };
test('server-only resume with all confirmations but no Finish opens the last feedback step', () => {
  const state = fresh(); complete(state);
  const loaded = createState(server(state), startedAt);
  assert.equal(loaded.index, 1); assert.equal(loaded.phase, 'feedback');
  nextQuestion(loaded, Date.parse(startedAt));
  assert.equal(loaded.finishPending, true); assert.equal(loaded.finishRecord.confirmedCount, 2);
});
for (const fixture of cases) test(fixture.name, () => assert.deepEqual(grade(fixture.question, fixture.answer), fixture.expected));

test('fullscreen exits require an active-to-inactive transition', () => {
  assert.equal(isFullscreenExit(false,false),false); assert.equal(isFullscreenExit(false,true),false);
  assert.equal(isFullscreenExit(true,true),false); assert.equal(isFullscreenExit(true,false),true);
});
test('drafts remain local; confirmations persist their original timing', () => {
  const state=fresh(); editAnswer(state,{answerCodes:['right']});
  assert.equal(hasPending(state),false); submitAnswer(state);
  assert.equal(state.pendingAnswers.length,1);
  assert.equal(Date.parse(state.pendingAnswers[0].clientAnsweredAt),Date.parse(startedAt));
  assert.equal(nextQuestion(state),true); assert.equal(state.index,1);
  assert.equal(deadlines(state)[0].reason,'timer_expired');
});
test('skip queues an explicit empty confirmation', () => {
  const state=fresh(); editAnswer(state,{answerCodes:['right']}); submitAnswer(state,'skipped');
  assert.deepEqual(state.pendingAnswers[0].answerCodes,[]); assert.equal(state.pendingAnswers[0].status,'skipped');
  assert.equal(grade(questions[0],state.items[0]).result,'unanswered');
});
test('timeout grades only confirmed answers and retains the unsubmitted draft locally', () => {
  const state=fresh(); editAnswer(state,{answerCodes:['right']}); finishTimed(state);
  assert.equal(state.items[0].status,'active'); assert.equal(state.finishPending,true);
  assert.equal(summarize(state.quiz.questions,state.items).score,'0');
  assert.equal(state.finishRecord.confirmedCount,0); assert.equal(state.finishRecord.clientFinishedAt,expiresAt);
});
test('expiry is checked before editing, submitting, or resuming', () => {
  for (const operation of [s=>editAnswer(s,{answerCodes:['right']},Date.parse(expiresAt)),s=>submitAnswer(s,'answered',Date.parse(expiresAt)),s=>recordActivity(s,Date.parse(expiresAt))]) {
    const state=fresh(); assert.equal(operation(state),false);
    assert.equal(state.finishReason,'timer_expired'); assert.equal(state.pendingAnswers.length,0);
  }
});
test('untimed meaningful activity extends local deadline but respects scheduled closing', () => {
  const state=fresh(); state.quiz.settings.timeLimitSec=null; state.expiresAt='2026-09-26T18:00:00Z';
  recordActivity(state,Date.parse(startedAt)+3600000);
  assert.equal(state.expiresAt,'2026-09-26T19:00:00.000Z'); assert.equal(state.activityPending,true);
  state.quiz.settings.closesAt='2026-09-26T12:00:00Z';
  recordActivity(state,Date.parse(startedAt)+3601000);
  assert.equal(state.expiresAt,'2026-09-26T12:00:00.000Z'); assert.equal(state.deadlineReason,'scheduled_close');
});
test('only authored timer and closing deadlines are visible', () => {
  const untimed=fresh(); untimed.quiz.settings.timeLimitSec=null; untimed.deadlineReason='stale_timeout';
  untimed.expiresAt='2026-09-26T18:00:00Z';
  assert.deepEqual(visibleDeadlines(untimed),[]);
  untimed.deadlineReason='scheduled_close'; untimed.quiz.settings.closesAt=untimed.expiresAt;
  assert.equal(visibleDeadlines(untimed)[0].label,'closingTimer');
  const timed=fresh(); assert.equal(visibleDeadlines(timed)[0].label,'quizTimer');
});
test('routine activity is internal pending work, not an answer upload', () => {
  const state=fresh(); state.quiz.settings.timeLimitSec=null; state.expiresAt='2026-09-26T18:00:00Z';
  recordActivity(state,Date.parse(startedAt)+1000);
  assert.equal(hasPending(state),true); assert.equal(hasUploadPending(state),false);
});
test('quit keeps the confirmed prefix, ignores the current draft and yields to expiry', () => {
  const empty=fresh(); assert.equal(quitQuiz(empty,Date.parse(startedAt)+1000),true);
  assert.equal(empty.finishReason,'quit'); assert.equal(empty.finishRecord.confirmedCount,0);

  const partial=fresh(); submit(partial); nextQuestion(partial); editAnswer(partial,{answerCodes:['wrong']},Date.parse(startedAt)+2000);
  assert.equal(quitQuiz(partial,Date.parse(startedAt)+3000),true);
  assert.equal(partial.finishRecord.confirmedCount,1); assert.equal(partial.pendingAnswers.length,1);
  assert.equal(summarize(partial.quiz.questions,partial.items).score,'1');

  const expired=fresh(); assert.equal(quitQuiz(expired,Date.parse(expiresAt)),false);
  assert.equal(expired.finishReason,'timer_expired');
});
test('legacy local state receives authenticated identity during recovery', () => {
  const legacyData=data(); delete legacyData.student;
  const state=createState(legacyData,startedAt,Date.parse(startedAt));
  assert.equal(state.student,null);
  const remote=server(state); remote.student={name:'<Alex>',email:'alex@example.test'};
  assert.equal(mergeServer(state,remote),true); assert.deepEqual(state.student,remote.student);
});
test('small acknowledgement removes only its operation and never resets a newer draft', () => {
  const state=fresh(); submit(state); nextQuestion(state);
  const operation=state.pendingAnswers[0]; editAnswer(state,{answerCodes:['wrong']});
  acknowledge(state,ack(state,operation),operation);
  assert.equal(state.pendingAnswers.length,0); assert.deepEqual(state.items[1].answerCodes,['wrong']); assert.equal(state.index,1);
});
test('resume preserves pending confirmations, current drafts and provisional results', () => {
  const state=fresh(); const remote=server(state); submit(state); nextQuestion(state); editAnswer(state,{textAnswer:'new draft'});
  remote.status='abandoned'; remote.result={confirmed:false,score:'0'}; remote.finishReason='timer_expired';
  assert.equal(mergeServer(state,remote),true);
  assert.equal(state.pendingAnswers.length,1); assert.equal(state.result,null); assert.equal(state.index,1);
  assert.equal(state.items[1].textAnswer,'new draft');
  finishTimed(state); const finish=clone(state.finishRecord);
  assert.equal(mergeServer(state,remote),true); assert.deepEqual(state.finishRecord,finish); assert.equal(state.finishPending,true);
});
test('a lost acknowledgement recovered from full state removes only matching confirmations', () => {
  const state=fresh(); submit(state); const remote=server(state); nextQuestion(state); submit(state);
  assert.equal(mergeServer(state,remote),true); assert.equal(state.pendingAnswers.length,1);
  assert.equal(state.pendingAnswers[0].questionId,'second');
});
test('changed saved answer pauses reconciliation without modifying local work', () => {
  const state=fresh(); submit(state); const snapshot=clone(state), remote=server(state);
  remote.items[0].answerCodes=['wrong']; assert.equal(mergeServer(state,remote),false); assert.deepEqual(state,snapshot);
});
test('serialized worker sends small confirmations then Finish without downloading the quiz', async () => {
  const state=fresh(); complete(state); const paths=[]; let inFlight=0,maxInFlight=0;
  const worker=new PlayerSync(()=>state,async(path,{body})=>{
    paths.push(path); inFlight++;maxInFlight=Math.max(maxInFlight,inFlight);await Promise.resolve();inFlight--;
    if(path.endsWith('/finish')) return {data:{...ack(state),status:'completed',finishReason:'completed',result:{...summarize(state.quiz.questions,state.items),confirmed:true}}};
    return {data:ack(state,{...body,questionId:path.split('/').at(-1)})};
  });
  try {
    await worker.flush(); assert.equal(maxInFlight,1);assert.equal(paths.length,3);assert.ok(paths[2].endsWith('/finish'));
    assert.equal(hasPending(state),false); assert.equal(state.result.confirmed,true);
  } finally {worker.stop();}
});
test('offline retry keeps timestamps and preserves answers created during a request', async () => {
  const state=fresh();submit(state);const original=clone(state.pendingAnswers[0]);let fail=true;
  const worker=new PlayerSync(()=>state,async(path,{body})=>{
    if(fail){fail=false;nextQuestion(state);submit(state);throw new Error('offline');}
    assert.ok(path.includes('/answers/'));
    return {data:ack(state,{...body,questionId:path.split('/').at(-1)})};
  });
  try {
    await worker.flush();assert.deepEqual(state.pendingAnswers[0],original);assert.equal(state.pendingAnswers.length,2);
    await worker.flush();assert.equal(state.pendingAnswers.length,0);
  } finally {worker.stop();}
});
test('full recovery and outgoing confirmations never overlap', async () => {
  const state=fresh(); const remote=server(state); submit(state);let requests=0;
  const worker=new PlayerSync(()=>state,async(path,{body})=>{
    requests++; if(!body){await Promise.resolve();return {data:remote};}
    return {data:ack(state,{...body,questionId:path.split('/').at(-1)})};
  });
  try {await Promise.all([worker.recover(),worker.flush()]);assert.equal(requests,2);assert.equal(hasPending(state),false);} finally {worker.stop();}
});
test('late abandoned acknowledgements do not discard the local final result or remaining queue', async () => {
  const state=fresh();complete(state);const worker=new PlayerSync(()=>state,async(path,{body})=>{
    if(path.endsWith('/finish'))return {data:{...ack(state),status:'completed',finishReason:'completed',lateSync:true,result:{...summarize(state.quiz.questions,state.items),confirmed:true}}};
    return {data:{...ack(state,{...body,questionId:path.split('/').at(-1)}),status:'abandoned',lateSync:true}};
  });
  try {await worker.flush();assert.equal(state.lateSync,true);assert.equal(state.result.score,'2');assert.equal(hasPending(state),false);} finally {worker.stop();}
});
test('activity coalesces without extending due to background retries', async () => {
  const state=fresh();state.quiz.settings.timeLimitSec=null;state.expiresAt='2026-09-26T18:00:00Z';
  recordActivity(state,Date.parse(startedAt)+1000);const at=state.clientActivityAt;let count=0;
  const worker=new PlayerSync(()=>state,async()=>{count++;return {data:ack(state)};});
  try {
    await worker.flush();assert.equal(count,1);assert.equal(state.activityPending,false);
    recordActivity(state,Date.parse(startedAt)+2000);await worker.flush();assert.equal(count,1);
    assert.notEqual(state.clientActivityAt,at);
  } finally {worker.stop();}
});
test('every visibility combination hides all feedback only when all flags are false', () => {
  for(const showScore of [true,false])for(const showAnswers of [true,false])for(const showExplain of [true,false]){
    assert.equal(receiptOnly({showScore,showAnswers,showExplain}),!(showScore||showAnswers||showExplain));
  }
});
test('practice never queues or sends answers and ignores unsubmitted drafts at timeout', async () => {
  const state=fresh();state.mode='practice';editAnswer(state,{answerCodes:['right']});finishTimed(state);
  const worker=new PlayerSync(()=>state,async()=>assert.fail('Practice sent data'));
  try {await worker.flush();assert.equal(hasPending(state),false);assert.equal(summarize(state.quiz.questions,state.items).score,'0');}finally{worker.stop();}
});
test('clock survives backgrounding and backwards wall changes', () => {
  const state=fresh();let wall=Date.parse(startedAt),monotonic=0;
  const clock=new PlayerClock(state,()=>monotonic,()=>wall);monotonic+=10000;wall-=10000;
  assert.equal(clock.now(),Date.parse(startedAt)+10000);
});
