import test from 'node:test';
import assert from 'node:assert/strict';
import {PlayerTabGuard} from '../../public/js/player-tab-guard.js';
import {createState, editAnswer, submitAnswer, nextQuestion} from '../../public/js/player-state.js';
import {PlayerSync} from '../../public/js/player-sync.js';

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
const payload=()=>({attemptId:'a',credential:'secret',status:'in_progress',answerVersion:1,startedAt:'2026-01-01T00:00:00Z',
  expiresAt:'2026-01-01T02:00:00Z',finishReason:null,lateSync:false,result:null,student:{name:'Student'},
  answers:[{questionId:'1',answerCode:'a',answeredAt:'2026-01-01T00:01:00.000Z'}],quiz:{schemaVersion:1,cover:null,
    quiz:{title:'Quiz',description:'',shareToken:'123456789',timeLimitSec:7200,feedback:'at_end',showScore:true,showAnswers:false,showExplain:false},
    questions:[{id:'1',content:'One',optionsLayout:'list',correctCode:'a',media:null,explanation:'',options:[{id:'11',code:'a',content:'A',media:null},{id:'12',code:'b',content:'B',media:null}]},
      {id:'2',content:'Two',optionsLayout:'list',correctCode:'a',media:null,explanation:'',options:[{id:'21',code:'a',content:'A',media:null},{id:'22',code:'b',content:'B',media:null}]}]}});
test('player resumes after the stored confirmed prefix',()=>{
  const state=createState(payload());
  assert.equal(state.index,1);assert.equal(state.phase,'answering');
  const complete=payload();complete.status='completed';complete.answerVersion=2;complete.finishReason='completed';complete.result={official:true};
  complete.answers.push({questionId:'2',answerCode:'a',answeredAt:'2026-01-01T00:02:00.000Z'});
  assert.equal(createState(complete).phase,'complete');
});
test('sync cannot send confirmed progress without tab ownership',async()=>{
  const state=createState({...payload(),answerVersion:0,answers:[]});
  editAnswer(state,'a',Date.parse('2026-01-01T00:01:00Z'));submitAnswer(state,false,Date.parse('2026-01-01T00:01:00Z'));
  let allowed=false,requests=0;
  const worker=new PlayerSync(()=>state,async()=>{requests++;throw new Error('Not expected');},{canSend:()=>allowed});
  await worker.flush();assert.equal(requests,0);
  worker.stop();
});
test('losing ownership during progress prevents a subsequent Finish request',async()=>{
  const state=createState({...payload(),answerVersion:0,answers:[]});
  editAnswer(state,'a',Date.parse('2026-01-01T00:01:00Z'));submitAnswer(state,false,Date.parse('2026-01-01T00:01:00Z'));
  let allowed=true,requests=0;
  const worker=new PlayerSync(()=>state,async()=>{
    requests++;allowed=false;
    return {data:{attemptId:state.attemptId,answerVersion:state.answerVersion,status:'in_progress'}};
  },{canSend:()=>allowed});
  await worker.flush();assert.equal(requests,1);worker.stop();
});
