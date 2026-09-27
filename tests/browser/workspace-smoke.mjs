// Real teacher views and assets with a loopback fixture API. Never uses the development database.
import http from 'node:http';
import fs from 'node:fs/promises';
import path from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const artifacts = await fs.mkdtemp('/private/tmp/edutest-workspace-browser-');
const chromePath = process.env.PLAYER_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const php = process.env.PLAYER_PHP || '/Applications/MAMP/bin/php/php8.4.17/bin/php';
const id = 'a'.repeat(32);
let origin, writes = 0, conflict = false;
let saved = {
  publicId: id, title: 'Exploring our world', mode: 'assessment', status: 'published', version: 1, revision: 1,
  hasStarted: true, resultsAvailable: true, resultsUrl: '/results/quizzes/' + id,
  shareUrl: '/q/123456789', description: 'A thoughtful geography assessment.', instructions: '',
  listed: false, cover: null, timeLimitMinutes: '10', timezone: 'Asia/Tashkent', opensAtLocal: '', closesAtLocal: '',
  passcode: {configured: false, action: 'unchanged'}, emailMode: 'optional', phoneMode: 'hidden',
  shuffleQuestions: false, shuffleOptions: false, feedback: 'at_end', showScore: true, showAnswers: false, showExplain: false, cheatCheck: false,
  mediaLimits: {imageBytes: 5242880, audioBytes: 20971520, serverBytes: 33554432},
  questions: [{id:'1',position:1,type:'single_choice',content:'What is the capital of France?',explanation:'Paris is the capital.',textAnswers:[],media:null,options:[
    {id:'1',code:'one',content:'Paris',isCorrect:true,media:null},{id:'2',code:'two',content:'Berlin',isCorrect:false,media:null}
  ]}]
};
function data(page, url) {
  const quiz = {publicId:id,title:saved.title,currentMode:saved.mode,status:url.searchParams.has('archived')?'archived':'published',builderUrl:url.searchParams.has('archived')?null:'/quizzes/'+id+'/edit',restoreUrl:'/dashboard?view=archived',url:'/results/quizzes/'+id};
  const filters = {q:'',view:'active',status:'',mode:'',reports:'',sort:'updated_desc',page:1};
  const row = {publicId:id,title:saved.title,mode:'assessment',status:'published',deleted:false,hasStarted:true,questionCount:20,
    assessmentSubmissions:128,inProgressAttempts:3,averagePercent:'78.50',practiceStarts:14,latestSubmission:'27 Sep 2026, 21:30',updatedAt:'27 Sep 2026, 20:40',editUrl:quiz.builderUrl,resultsUrl:quiz.url};
  const attempt = {name:'Alex Morgan',email:'alex@example.test',phone:null,ip:'192.0.2.1',agent:'Fixture browser',status:'submitted',paperRevision:1,score:'15.70',maxScore:'20.00',percent:'78.50',duration:'5m 10s',finishReason:'completed',startedAt:{display:'27 Sep 2026, 21:24'},submittedAt:{display:'27 Sep 2026, 21:30'},integrityCount:0,url:'/results/attempts/'+id};
  const report = {quiz, metrics:{finalizedAttempts:128,inProgressAttempts:3,averagePercent:'78.50'}, attempts:[attempt],
    filters:{query:'',status:'finalized',dateFrom:null,dateTo:null,minScore:null,maxScore:null,integrity:'all',sort:'newest'},
    pagination:{page:1,pageCount:1,total:1},exportUrl:'/results/quizzes/'+id+'/export.csv',attempt,
    paper:{revision:1,title:saved.title,mode:'assessment',description:'Original quiz definition.',instructions:''},policies:[],questions:[],events:[],timezone:'Asia/Tashkent'};
  if(page==='attempt') delete report.exportUrl;
  return {title:'Teacher workspace — EduTest',user:{display_name:'Sarah Williams',timezone:'Asia/Tashkent'},
    quizWorkspace:page!=='dashboard',builderHeader:page==='builder',quiz:saved,report,
    dateLabel:'Sunday, 27 September',
    dashboard:{metrics:{totalQuizzes:12,publishedQuizzes:8,assessmentSubmissions:128,inProgressAttempts:3,averagePercent:'78.50',practiceStarts:14},
      library:{rows:url.searchParams.has('empty')?[]:[row,{...row,publicId:'b'.repeat(32),title:'Anonymous revision practice',mode:'practice',resultsUrl:null,assessmentSubmissions:0,inProgressAttempts:0,averagePercent:null,latestSubmission:null}],filters,view:'active',pagination:{page:1,pageCount:1,total:2}}}};
}
const server = http.createServer(async (request, response) => {
  try {
    const url = new URL(request.url, origin);
    if (url.pathname.startsWith('/assets/')) {
      if (url.pathname.includes('..')) {response.writeHead(404).end();return;}
      response.writeHead(200, {'Content-Type':url.pathname.endsWith('.css')?'text/css':'text/javascript'});
      response.end(await fs.readFile(path.join(repo,'public',url.pathname))); return;
    }
    if (url.pathname === '/favicon.ico') {response.writeHead(204).end();return;}
    if (url.pathname.startsWith('/api/')) {
      const buffers=[]; for await (const chunk of request) buffers.push(chunk);
      if (request.method === 'PUT') {
        writes++;
        if(conflict) {conflict=false;response.writeHead(409,{'Content-Type':'application/json'}).end(JSON.stringify({error:{code:'version_conflict',message:'Fixture conflict',version:2},meta:{}}));return;}
        saved={...JSON.parse(Buffer.concat(buffers).toString()),version:saved.version+1};
        saved.resultsAvailable=saved.mode==='assessment'; saved.resultsUrl=saved.resultsAvailable?'/results/quizzes/'+id:null;
      }
      response.writeHead(200,{'Content-Type':'application/json'}).end(JSON.stringify({data:{quiz:saved},meta:{csrfToken:'fixture-token'}})); return;
    }
    const page=url.pathname==='/dashboard'?'dashboard':url.pathname.includes('/edit')?'builder':url.pathname.includes('/attempts/')?'attempt':'responses';
    const html=execFileSync(php,[path.join(repo,'tests/browser/render-teacher.php'),origin+'/'],{cwd:repo,input:JSON.stringify({page,data:data(page,url)}),maxBuffer:4*1024*1024});
    response.writeHead(200,{'Content-Type':'text/html','Cache-Control':'no-store'}).end(html);
  } catch(error) {response.writeHead(500).end(String(error));}
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
origin = `http://127.0.0.1:${server.address().port}`;
const profile = path.join(artifacts, 'chrome-profile');
const browser = spawn(chromePath, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {stdio: 'ignore'});
let socket;
const pending = new Map(); let sequence = 0, sessionId;
const errors = [], networkFailures = [];
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(check, label, timeout = 15000) { const end = Date.now() + timeout; while (Date.now() < end) { if (await check()) return; await pause(100); } throw new Error(`Timed out: ${label}`); }
function command(method, params = {}, session = sessionId) {
  const id = ++sequence;
  return new Promise((resolve, reject) => { pending.set(id, {resolve, reject}); socket.send(JSON.stringify({id, method, params, ...(session ? {sessionId: session} : {})})); });
}
async function evaluate(expression) {
  const response = await command('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
  if (response.exceptionDetails) throw new Error(response.exceptionDetails.exception?.description || response.exceptionDetails.text);
  return response.result.value;
}
async function screenshot(name) {
  const result = await command('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
  await fs.writeFile(path.join(artifacts, name + '.png'), Buffer.from(result.data, 'base64'));
}
async function viewport(width, height) { await command('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 600}); }

async function navigate(route) {
  await command('Page.navigate', {url:origin+route});
  await until(()=>evaluate('document.readyState === "complete"'), 'document loaded');
}
async function noOverflow() {
  assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), true, 'No horizontal page overflow');
}
try {
  let port;
  await until(async () => { try { port = (await fs.readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; return !!port; } catch (_) { return false; } }, 'Chrome startup');
  const version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json();
  socket = new WebSocket(version.webSocketDebuggerUrl);
  await new Promise(resolve => socket.addEventListener('open', resolve, {once: true}));
  socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.id) { const handler = pending.get(message.id); pending.delete(message.id); if (message.error) handler?.reject(new Error(message.error.message)); else handler?.resolve(message.result); }
    else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
    else if (message.method === 'Network.loadingFailed') networkFailures.push(message.params);
  });
  const target = await command('Target.createTarget', {url: 'about:blank'}, null);
  sessionId = (await command('Target.attachToTarget', {targetId: target.targetId, flatten: true}, null)).sessionId;
  await command('Page.enable'); await command('Runtime.enable'); await command('Network.enable');

  for (const width of [1440, 1024, 850, 768, 390, 320]) {
    await viewport(width,1000);
    await navigate('/dashboard');
    await until(()=>evaluate('!!document.querySelector("[data-account-menu]")'), 'dashboard');
    await noOverflow();
    assert.equal(await evaluate('!!document.querySelector(".teacher-sidebar")'), false);
    await screenshot('dashboard-'+width);
    await evaluate('document.querySelector(".dashboard-library").scrollIntoView({behavior:"instant"})');
    await evaluate('document.querySelector("[data-menu-toggle]").click()');
    assert.equal(await evaluate('document.querySelector("[data-menu-toggle]").getAttribute("aria-expanded")'),'true');
    await screenshot('library-'+width);
    await command('Input.dispatchKeyEvent',{type:'rawKeyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    assert.equal(await evaluate('document.querySelector("[data-menu-toggle]").getAttribute("aria-expanded")'),'false');
    await evaluate('document.querySelector("[data-open-create]").focus();document.querySelector("[data-open-create]").click()');
    await until(()=>evaluate('document.querySelector("#create-quiz-dialog").open'), 'create dialog');
    await screenshot('create-'+width);
    await evaluate('document.querySelectorAll("[data-close-create]")[1].click()');
    await until(()=>evaluate('!document.querySelector("#create-quiz-dialog").open'), 'cancel empty title');
    await until(()=>evaluate('document.activeElement.matches("[data-open-create]")'), 'opener focus');
    await evaluate('document.querySelector("[data-open-create]").click()');
    await evaluate('document.querySelector("[data-close-create]").click()');
    await until(()=>evaluate('!document.querySelector("#create-quiz-dialog").open'), 'close empty dialog');
    await evaluate('document.querySelector("[data-open-create]").click()');
    await command('Input.dispatchKeyEvent',{type:'rawKeyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await until(()=>evaluate('!document.querySelector("#create-quiz-dialog").open'), 'Escape dialog');
    await evaluate('document.querySelector("[data-account-menu] summary").click()');
    await command('Input.dispatchKeyEvent',{type:'rawKeyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    assert.equal(await evaluate('document.querySelector("[data-account-menu]").open'),false);
    await navigate('/quizzes/'+id+'/edit');
    await until(()=>evaluate('!!document.querySelector(".builder-bar")'), 'builder');
    await noOverflow();
    assert.equal(await evaluate('!!document.querySelector(".teacher-topbar")'),false);
    assert.equal(await evaluate('document.querySelector(".builder-actions button:nth-child(2)").getBoundingClientRect().height >= 38'),true);
    assert.equal(await evaluate('document.querySelector(".builder-title a").getBoundingClientRect().width > 0'),true);
    await screenshot('builder-'+width);
    await navigate('/results/quizzes/'+id);
    await until(()=>evaluate('!!document.querySelector(".results-workspace")'), 'responses');
    await noOverflow();
    assert.equal(await evaluate('document.querySelector(".builder-view-nav [aria-current]").textContent.trim()'),'Responses');
    await screenshot('responses-'+width);
    await evaluate('document.querySelector(".results-list").scrollIntoView({behavior:"instant"})');
    await noOverflow();
    await screenshot('table-'+width);
    await navigate('/results/attempts/'+id);
    await until(()=>evaluate('!!document.querySelector(".attempt-review")'),'attempt review');
    await noOverflow();
    assert.equal(await evaluate('document.querySelector(".builder-actions").textContent.includes("Back to responses")'),true);
    await screenshot('attempt-'+width);
  }
  await navigate('/results/quizzes/'+id+'?archived');
  await until(()=>evaluate('!!document.querySelector("#builder-unavailable")'), 'restore notice');
  assert.equal(await evaluate('!!document.querySelector(".builder-view-nav span[aria-disabled]")'),true);
  await navigate('/dashboard?empty');
  await until(()=>evaluate('document.querySelector(".quiz-list").textContent.includes("No matching quizzes")'), 'empty list');
  await navigate('/quizzes/'+id+'/edit');
  await until(()=>evaluate('!!document.querySelector(".builder-bar")'), 'builder ready');
  // Published edits do not autosave. Dirty navigation keeps its native warning.
  await evaluate('document.querySelector(".question-editor textarea").focus()');
  await command('Input.insertText',{text:'Updated question'});
  await pause(1200);
  assert.equal(writes,0);
  assert.equal(await evaluate('document.querySelector(".builder-title small").textContent'),'Unsaved changes');
  const dialogs=[];
  socket.addEventListener('message',event=>{const msg=JSON.parse(event.data);if(msg.method==='Page.javascriptDialogOpening'){dialogs.push(msg.params.type);command('Page.handleJavaScriptDialog',{accept:false}).catch(()=>{});}});
  await evaluate('document.querySelector(".builder-view-nav a:last-child").click()');
  await until(()=>dialogs.includes('beforeunload'),'dirty navigation warning');
  assert.equal(await evaluate('location.pathname'),'/quizzes/'+id+'/edit');
  await evaluate('document.querySelector(".builder-actions button:nth-child(2)").click()');
  await until(()=>evaluate('document.querySelector(".builder-title small").textContent.startsWith("Saved")'),'manual save');
  assert.equal(writes,1);
  conflict=true;
  await evaluate('document.querySelector(".question-editor textarea").focus()');
  await command('Input.insertText',{text:'Conflicting update'});
  await evaluate('document.querySelector(".builder-actions button:nth-child(2)").click()');
  await until(()=>evaluate('!!document.querySelector("dialog[open]")'),'conflict dialog');
  await screenshot('conflict-mobile');
  await evaluate('document.querySelector("dialog[open] button").click()');
  await until(()=>evaluate('!document.querySelector("dialog[open]")'),'reload server version');
  // Saved Practice without historical attempts omits navigation.
  saved={...saved,mode:'practice',resultsAvailable:false,resultsUrl:null,emailMode:'hidden'};
  await navigate('/quizzes/'+id+'/edit');
  await until(()=>evaluate('!!document.querySelector(".builder-bar")'),'practice builder');
  assert.equal(await evaluate('!!document.querySelector(".builder-view-nav")'),false);
  await command('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
  assert.equal(await evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'),true);
  assert.deepEqual(errors,[]);
  console.log(JSON.stringify({passed:true,widths:[1440,1024,850,768,390,320],checks:['actual PHP views','shared headers','empty states','dialog cancel/close/Escape/focus','account Escape','lifecycle menus','attempt review','response tables','conflict dialog','manual save','dirty navigation warning','Practice tabs','overflow','reduced motion'],artifacts},null,2));
} catch(error) {
  console.error(JSON.stringify({error:error.message,errors,networkFailures,artifacts},null,2));
  if(socket) await screenshot('failure').catch(()=>{});
  throw error;
} finally {
  socket?.close(); browser.kill('SIGTERM'); server.close();
}
