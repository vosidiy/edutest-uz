import assert from 'node:assert/strict';
import {test} from 'node:test';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/js/builder.js', import.meta.url), 'utf8');
const clone = value => JSON.parse(JSON.stringify(value));
function fixture() {
  let component, request;
  const Vue = {createApp(options) { component = options; return {mount() {}}; }};
  const root = {dataset:{publicId:'fixture',workspaceMessages:JSON.stringify({coverFailed:'Cover retained.',invalidTitle:'Invalid title'})}};
  const api = {request:(...args)=>request(...args),toast(){}};
  const revoked = [];
  vm.runInNewContext(source, {Vue, EduTestApi:api, window:{Vue,EduTestApi:api,confirm:()=>true},
    document:{querySelector:()=>root}, URL:{revokeObjectURL:url=>revoked.push(url)}, FormData,
    setTimeout,clearTimeout,console});
  const app = component.data();
  app.$nextTick = async fn => {fn?.();};
  app.$refs = {detailsDialog:{showModal(){},close(){}},editTitle:{focus(){}},detailsTitle:{focus(){}}};
  Object.entries(component.methods).forEach(([name,fn])=>{app[name]=fn.bind(app);});
  Object.entries(component.computed).forEach(([name,fn])=>Object.defineProperty(app,name,{get:()=>fn.call(app)}));
  const quiz={publicId:'fixture',title:'Quiz',description:'',status:'published',updatedAt:'2026-10-31T00:00:00+00:00',timeLimitSec:7200,timeLimitMinutes:120,
    askEmail:false,askPhone:false,drawCount:null,shuffleQuestions:false,shuffleOptions:false,feedback:'at_end',
    showScore:true,showAnswers:false,showExplain:false,cover:null,mediaLimits:{imageBytes:5242880,audioBytes:20971520},
    questions:[{id:'1',content:'Question',optionsLayout:'list',explanation:'',media:null,
      options:[{id:'11',code:'a',content:'Yes',isCorrect:true,media:null},{id:'12',code:'b',content:'No',isCorrect:false,media:null}]}]};
  app.applyServerQuiz(clone(quiz)); app.loading=false; app.hydrating=false;
  return {app,quiz,revoked,setRequest:fn=>{request=fn;}};
}
function deferred() {let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};}
function response(payload) {
  const quiz=clone(payload); quiz.updatedAt='2026-10-31T00:00:01+00:00';
  quiz.questions.forEach((q,i)=>{q.id ||= String(100+i);q.options.forEach((o,j)=>{o.id ||= String(1000+i*10+j);});});
  return {data:{quiz}};
}

test('explicit clean saves send a request and reverting restores clean state', async()=>{
  const {app,setRequest}=fixture();let requests=0;setRequest(async(_url,options)=>{requests++;return response(JSON.parse(options.body));});
  assert.equal(app.hasChanges,false);
  assert.equal(await app.save(true),true);assert.equal(requests,1);
  assert.equal(app.quiz.updatedAt,'2026-10-31T00:00:01+00:00');
  app.quiz.title='Changed';app.markDirty();
  assert.equal(app.hasChanges,true);assert.equal(app.saveState,'dirty');
  app.quiz.title='Quiz';app.markDirty();assert.equal(app.hasChanges,false);assert.equal(app.saveState,'saved');
});
test('one save pipeline preserves newer text, layouts, reorders, deletions and new row identities',async()=>{
  const {app,setRequest}=fixture(); const gate=deferred();let sent,requests=0;
  app.quiz.questions[0].id=null; app.quiz.questions[0].options[0].id=null;
  setRequest((_url,options)=>{requests++;sent=JSON.parse(options.body);return gate.promise;});
  const original=app.quiz.questions[0], option=original.options[0];
  const saving=app.save(true);
  assert.equal(app.save(true),saving);assert.equal(app.saveState,'saving');assert.equal(requests,1);
  app.quiz.title='Newer title';original.content='Newer question';option.content='Newer option';
  original.options.splice(1,1);
  app.quiz.questions.unshift({id:null,content:'Added while saving',optionsLayout:'grid',explanation:'',media:null,
    options:[{id:null,content:'A',isCorrect:true,media:null},{id:null,content:'B',isCorrect:false,media:null}]});
  gate.resolve(response(sent));assert.equal(await saving,false);
  assert.equal(app.quiz.title,'Newer title');assert.equal(original.content,'Newer question');
  assert.equal(original.id,'100');assert.equal(option.id,'1000');assert.equal(option.content,'Newer option');
  assert.equal(original.options.length,1);assert.equal(app.quiz.questions[0].id,null);
  assert.equal(app.quiz.questions[0].optionsLayout,'grid');assert.equal(app.saveState,'dirty');
  setRequest(async(_url,options)=>response(JSON.parse(options.body)));
  assert.equal(await app.save(true),true);assert.equal(app.hasChanges,false);
});
test('cover failure retains file and retries only media after a successful aggregate save',async()=>{
  const {app,setRequest}=fixture();const calls=[];
  app.quiz.title='Changed';app.pendingCover={action:'upload',file:new Blob(['image']),url:'blob:pending'};
  let fail=true;
  setRequest(async(url,options)=>{
    calls.push(options.method);
    if(options.method==='PUT')return response(JSON.parse(options.body));
    if(fail)throw new Error('Offline');
    return {data:{updatedAt:'2026-10-31T00:00:02+00:00',media:{type:'image',url:'/signed-cover'}}};
  });
  assert.equal(await app.save(true),false);assert.deepEqual(calls,['PUT','POST']);
  assert.equal(app.pendingCover.url,'blob:pending');assert.match(app.globalError,/Cover retained/);
  fail=false;assert.equal(await app.save(),true);
  assert.deepEqual(calls,['PUT','POST','POST']);assert.equal(app.pendingCover,null);
  assert.equal(app.quiz.updatedAt,'2026-10-31T00:00:02+00:00');
});
test('a newer cover applied during upload is not cleared by the old acknowledgement',async()=>{
  const {app,setRequest}=fixture();const gate=deferred();
  app.pendingCover={action:'upload',file:new Blob(['old']),url:'blob:old'};
  setRequest(()=>gate.promise);
  const saving=app.save(true);
  app.pendingCover={action:'remove'};
  gate.resolve({data:{updatedAt:'2026-10-31T00:00:02+00:00',media:{type:'image',url:'/old-cover'}}});
  assert.equal(await saving,false);assert.equal(app.pendingCover.action,'remove');
  setRequest(async(url,options)=>{assert.equal(options.method,'DELETE');assert.doesNotMatch(url,/version=/);return {data:{updatedAt:'2026-10-31T00:00:03+00:00',media:null}};});
  assert.equal(await app.save(),true);assert.equal(app.quiz.cover,null);
});
test('validation failures preserve local changes and use the retry state',async()=>{
  const {app,setRequest}=fixture();app.quiz.title='';
  setRequest(async()=>{throw Object.assign(new Error('Title required'),{status:422,fields:{title:'Required'}});});
  assert.equal(await app.save(true),false);assert.equal(app.saveState,'retry');assert.equal(app.quiz.title,'');
});
test('details cancel retains previous staged cover; URL cleanup never revokes a live preview',()=>{
  const {app,revoked}=fixture();
  app.pendingCover={action:'upload',url:'blob:pending'};
  app.coverUrls.add('blob:pending');app.coverUrls.add('blob:dialog');
  app.detailsOpen=true;app.detailsCover={action:'upload',url:'blob:dialog'};
  app.cleanCoverUrls();assert.deepEqual(revoked,[]);
  app.detailsClosed();assert.deepEqual(revoked,['blob:dialog']);assert.equal(app.pendingCover.url,'blob:pending');
  app.openDetails();app.detailsTitle='   ';app.applyDetails();assert.equal(app.detailsError,'Invalid title');
  app.detailsTitle='Updated';app.applyDetails();assert.equal(app.quiz.title,'Updated');assert.equal(app.hasChanges,true);
});

test('a queued close event cannot discard a newly reopened details dialog',()=>{
  const {app}=fixture();
  app.$refs.detailsDialog.open=true;
  app.detailsOpen=true;app.detailsTitle='New opening';
  app.detailsCover={action:'upload',url:'blob:new'};
  app.detailsClosed();
  assert.equal(app.detailsOpen,true);assert.equal(app.detailsCover.url,'blob:new');
  app.$refs.detailsDialog.open=false;app.detailsClosed();
  assert.equal(app.detailsOpen,false);assert.equal(app.detailsCover,null);
});

test('manual save persists staged status and preserves edits made during its response',async()=>{
  const {app,setRequest}=fixture();const gate=deferred();
  app.setActive(false);
  setRequest(()=>gate.promise);
  const saving=app.save(true);
  assert.equal(app.saving,true);
  const server=clone(app.quiz);server.updatedAt='2026-10-31T00:00:02+00:00';
  app.quiz.questions[0].content='Edited during save';app.markDirty();
  gate.resolve({data:{quiz:server}});assert.equal(await saving,false);
  assert.equal(app.quiz.status,'draft');
  assert.equal(app.quiz.questions[0].content,'Edited during save');
  assert.equal(app.saveState,'dirty');assert.equal(app.saving,false);
});

test('media preparation saves pending edits before returning the persisted endpoint',async()=>{
  const {app,setRequest}=fixture();const calls=[];
  app.quiz.questions[0].id=null;
  app.quiz.title='Latest saved title';
  app.markDirty();
  setRequest(async(url,options)=>{
    calls.push({url,method:options.method});
    return response(JSON.parse(options.body));
  });
  const endpoint=await app.prepareMedia(0,null);
  assert.deepEqual(calls.map(call=>call.method),['PUT']);
  assert.equal(endpoint,'/api/v1/quizzes/fixture/questions/100/media');
  assert.equal(app.hasChanges,false);
});

test('manual save fills blank choices and prevents removal below two',async()=>{
  const {app,setRequest}=fixture();
  app.quiz.questions[0].options[0].content='';
  app.quiz.questions[0].options.splice(1,1);
  app.quiz.questions[0].options[0].isCorrect=false;
  app.markDirty();
  setRequest(async(_url,options)=>response(JSON.parse(options.body)));
  assert.equal(await app.save(true),true);
  assert.deepEqual(app.quiz.questions[0].options.map(option=>option.content),['Option 1','Option 2']);
  assert.deepEqual(app.quiz.questions[0].options.map(option=>option.isCorrect),[true,false]);
  app.removeOption(0);
  assert.equal(app.quiz.questions[0].options.length,2);
  app.addOption();
  app.setCorrect(2,true);
  app.removeOption(2);
  assert.deepEqual(app.quiz.questions[0].options.map(option=>option.isCorrect),[true,false]);
});
