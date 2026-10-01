import test from 'node:test';
import assert from 'node:assert/strict';
import {PlayerTabGuard} from '../../public/assets/js/player-tab-guard.js';
import {createState, editAnswer, submitAnswer, nextQuestion} from '../../public/assets/js/player-state.js';
import {PlayerSync} from '../../public/assets/js/player-sync.js';

const turn = () => new Promise(resolve => setImmediate(resolve));
function manager() {
  const held = new Set();
  return {held, async request(name, options, callback) {
    assert.equal(options.ifAvailable,true);assert.equal(options.mode,'exclusive');
    if(held.has(name))return callback(null);
    held.add(name);
    try {return await callback({name});} finally {held.delete(name);}
  }};
}
test('same quiz excludes other tabs while different quizzes/profiles remain independent',async()=>{
  const locks=manager(), first=new PlayerTabGuard('123',locks), second=new PlayerTabGuard('123',locks);
  const other=new PlayerTabGuard('456',locks), profile=new PlayerTabGuard('123',manager());
  assert.equal(await first.acquire(),true);assert.equal(await second.acquire(),false);
  assert.equal(await other.acquire(),true);assert.equal(await profile.acquire(),true);
  first.release();await turn();assert.equal(await second.acquire(),true);
  second.release();other.release();profile.release();await turn();assert.equal(locks.held.size,0);
});
test('simultaneous acquisition has one winner and repeated calls share acquisition',async()=>{
  const locks=manager(), a=new PlayerTabGuard('q',locks), b=new PlayerTabGuard('q',locks);
  const results=await Promise.all([a.acquire(),b.acquire()]);
  assert.deepEqual(results,[true,false]);assert.equal(await a.acquire(),true);
  a.release();b.release();
});
test('missing/denied lock API falls back explicitly, but an occupied lock does not',async()=>{
  for(const locks of [null,{request(){throw new DOMException('Denied','SecurityError');}},{request(){return Promise.reject(new Error('Denied'));}}]){
    const guard=new PlayerTabGuard('q',locks);
    assert.equal(await guard.acquire(),true);assert.equal(guard.unavailable,true);assert.equal(guard.allowed,true);
    guard.release();assert.equal(guard.allowed,false);
  }
  const guard=new PlayerTabGuard('q',{request:async(_n,_o,callback)=>callback(null)});
  assert.equal(await guard.acquire(),false);assert.equal(guard.unavailable,false);
});
test('page exit during acquisition cannot activate an old page or leave a lock held',async()=>{
  let callback;
  const guard=new PlayerTabGuard('q',{request:(_n,_o,fn)=>{callback=fn;return Promise.resolve();}});
  const pending=guard.acquire();guard.release();await callback({name:'q'});
  assert.equal(await pending,false);assert.equal(guard.allowed,false);
});
test('player resumes at the first server-reported unconfirmed item',()=>{
  const questions=[{id:'large-9007199254740993'},{id:'2'},{id:'3'}];
  const payload={mode:'assessment',startedAt:'2026-01-01T00:00:00Z',quiz:{settings:{timeLimitSec:120},questions},items:[
    {questionId:questions[0].id,status:'locked',answerStatus:'answered',answerCodes:[],textAnswer:''},{questionId:'2',status:'active',answerStatus:'not_reached',answerCodes:[],textAnswer:''},{questionId:'3',status:'pending',answerStatus:'not_reached',answerCodes:[],textAnswer:''}]};
  assert.equal(createState(payload,payload.startedAt).index,1);
  payload.items[1].status='locked';payload.items[1].answerStatus='answered';
  const resumed=createState(payload,payload.startedAt);
  assert.equal(resumed.index,2);assert.equal(resumed.phase,'answering');
  payload.result={confirmed:true};payload.status='completed';
  assert.equal(createState(payload,payload.startedAt).phase,'complete');
});
test('sync cannot send drafts or integrity events without tab ownership',async()=>{
  const startedAt='2026-01-01T00:00:00Z';
  const state=createState({mode:'assessment',attemptId:'a',version:1,startedAt,quiz:{settings:{timeLimitSec:120},questions:[{id:'1'},{id:'2'}]}},startedAt);
  editAnswer(state,{answerCodes:['a']});submitAnswer(state);
  state.events.push({key:'event'});
  let allowed=false,requests=0;
  const worker=new PlayerSync(()=>state,async()=>{requests++;throw new Error('Not expected');},{canSend:()=>allowed});
  await worker.flush();assert.equal(requests,0);
  worker.stop();
});
test('losing ownership during one sync prevents subsequent event requests',async()=>{
  const startedAt='2026-01-01T00:00:00Z';
  const state=createState({mode:'assessment',attemptId:'a',version:1,startedAt,quiz:{settings:{timeLimitSec:120},questions:[{id:'1'}]}},startedAt);
  editAnswer(state,{answerCodes:['a']});submitAnswer(state);nextQuestion(state);
  state.events.push({key:'event'});
  let allowed=true,requests=0;
  const worker=new PlayerSync(()=>state,async()=>{
    requests++;allowed=false;
    return {data:{attemptId:state.attemptId,questionId:state.pendingAnswers[0].questionId,answerStatus:'answered',status:'in_progress',clientActivityAt:state.clientActivityAt,expiresAt:state.expiresAt}};
  },{canSend:()=>allowed});
  await worker.flush();assert.equal(requests,1);assert.equal(state.events.length,1);worker.stop();
});
