// No npm dependencies. Actual PHP views/CSS/JS with a loopback fixture API, never the MAMP database.
import http from 'node:http';
import fs from 'node:fs/promises';
import path from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {summarize} from '../../public/assets/js/player-scoring.js';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const artifacts = await fs.mkdtemp('/private/tmp/edutest-player-browser-');
const chromePath = process.env.PLAYER_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const php = process.env.PLAYER_PHP || 'php';
const token = 'a'.repeat(64);
let mode = 'assessment', feedback = 'after_each', visibility = true, integrity = true, timerSeconds = 600, official = null, forceConflict = false, loseStartResponse = false, starts = 0;
const requests = [];
const quiz = () => ({title: 'A little curiosity goes a long way', description: 'Explore geography in three quick questions. Take your time, trust what you know, and learn something new.',
  instructions: 'Choose your answer, then submit it. You cannot return to a submitted question.', teacher: 'Sarah Williams', questionCount: 3, timeLimitMinutes: timerSeconds === null ? null : String(timerSeconds / 60),
  opensAt: null, closesAt: null, passcodeRequired: false, availability: 'available', shareToken: token, emailMode: 'optional', phoneMode: 'hidden', cheatCheck: integrity, cover: null, mode});
const questions = () => [
  {id: '9007199254740993', type: 'single_choice', content: 'What is the capital of France?', media: null, explanation: 'Paris has been the political and cultural centre of France for centuries.', acceptedAnswers: [], correctCodes: ['paris'],
    options: [{id: '11', code: 'berlin', content: 'Berlin', media: null}, {id: '12', code: 'paris', content: 'Paris', media: null}, {id: '13', code: 'rome', content: 'Rome', media: null}, {id: '14', code: 'madrid', content: 'Madrid', media: null}]},
  {id: '2', type: 'multi_select', content: 'Which of these are continents?', media: null, explanation: 'Asia and Africa are continents. Paris is a city.', acceptedAnswers: [], correctCodes: ['asia', 'africa'],
    options: [{id: '21', code: 'asia', content: 'Asia', media: null}, {id: '22', code: 'africa', content: 'Africa', media: null}, {id: '23', code: 'city', content: 'Paris', media: null}]},
  {id: '3', type: 'short_text', content: 'What is the capital of Uzbekistan?', media: null, explanation: 'Tashkent is the capital and largest city of Uzbekistan.', acceptedAnswers: ['Tashkent', 'Toshkent'], correctCodes: [], options: []}
];
const json = (response, data, status = 200) => { response.writeHead(status, {'Content-Type': 'application/json', 'Cache-Control': 'no-store'}); response.end(JSON.stringify({data, meta: {timestamp: new Date().toISOString()}})); };
let origin;
const server = http.createServer(async (request, response) => {
  try {
    const url = new URL(request.url, origin);
    if (url.pathname.startsWith('/assets/')) {
      const relative = url.pathname.slice(1);
      if (relative.includes('..')) { response.writeHead(404).end(); return; }
      response.writeHead(200, {'Content-Type': relative.endsWith('.css') ? 'text/css' : 'text/javascript'});
      response.end(await fs.readFile(path.join(repo, 'public', relative))); return;
    }
    if (url.pathname === '/favicon.ico') { response.writeHead(204).end(); return; }
    if (url.pathname.startsWith('/q/')) {
      const page = url.pathname.endsWith('/play') ? 'play' : url.pathname.endsWith('/results') ? 'results' : 'intro';
      const html = execFileSync(php, [path.join(repo, 'tests/browser/render.php'), origin + '/'], {cwd: repo, input: JSON.stringify({page, quiz: quiz()}), maxBuffer: 2 * 1024 * 1024});
      response.writeHead(200, {'Content-Type': 'text/html', 'Cache-Control': 'no-store'}); response.end(html); return;
    }
    requests.push({method: request.method, path: url.pathname});
    const buffers = []; for await (const chunk of request) buffers.push(chunk);
    const body = buffers.length ? JSON.parse(Buffer.concat(buffers).toString()) : {};
    if (url.pathname.includes('/tickets/')) { json(response, {mode, ticket: 'fixture-admission', attemptId: mode === 'assessment' ? 'fixture' : null, credential: 'fixture-credential'}); return; }
    if (url.pathname.endsWith('/starts')) {
      starts++;
      const now = new Date().toISOString();
      official = {mode, attemptId: mode === 'assessment' ? 'fixture' : null, credential: 'fixture-credential', status: 'in_progress', startedAt: now,
        expiresAt: new Date(Date.now() + (timerSeconds ?? 28800) * 1000).toISOString(), deadlineReason: timerSeconds === null ? 'stale_timeout' : 'timer_expired', result: null, finishReason: null,
        ...(mode === 'assessment' ? {student:{name:body.name,email:body.email || null}} : {}),
        quiz: {...quiz(), settings: {feedback, timeLimitSec: timerSeconds, closesAt: null, ...(typeof visibility === 'boolean' ? {showScore: visibility, showAnswers: visibility, showExplain: visibility} : visibility), cheatCheck: integrity}, questions: questions()},
        items: questions().map((question, index) => ({questionId: question.id, status: index === 0 ? 'active' : 'pending', answerStatus: 'not_reached', answerCodes: [], textAnswer: ''}))};
      if (loseStartResponse) { loseStartResponse = false; response.writeHead(200, {'Content-Type': 'application/json'}).end('{"data":'); return; }
      json(response, official); return;
    }
    if (url.pathname.includes('/assessments/') && url.pathname.includes('/answers/')) {
      if (forceConflict) {
        forceConflict = false;
        Object.assign(official.items[0], {status: 'locked', answerStatus: 'answered', answerCodes: ['berlin'], textAnswer: ''});
        response.writeHead(409, {'Content-Type': 'application/json'}).end(JSON.stringify({error: {code: 'progress_conflict', message: 'Conflict'}, meta: {timestamp: new Date().toISOString()}})); return;
      }
      const questionId = decodeURIComponent(url.pathname.split('/').pop());
      const item = official.items.find(row => row.questionId === questionId);
      Object.assign(item, {answerCodes: body.answerCodes || [], textAnswer: body.textAnswer || '', clientAnsweredAt: body.clientAnsweredAt, answerStatus: body.status, status: 'locked'});
      const next = official.items.find(row => row.status === 'pending');
      if (next) next.status = 'active';
      json(response, {attemptId: official.attemptId, questionId, answerStatus: body.status, status: official.status, expiresAt: official.expiresAt, deadlineReason: official.deadlineReason, clientActivityAt: body.clientActivityAt, lateSync: official.status === 'abandoned', receivedAt: new Date().toISOString()}); return;
    }
    if (url.pathname.endsWith('/finish')) {
      const confirmed = official.items.filter(item => item.status === 'locked').length;
      if ((body.finishReason === 'completed' && confirmed !== official.items.length) || confirmed !== body.confirmedCount) {
        response.writeHead(409, {'Content-Type': 'application/json'}).end(JSON.stringify({error: {code: 'not_finished', message: 'Not finished'}, meta: {timestamp: new Date().toISOString()}})); return;
      }
      official.finishReason = body.finishReason; official.status = 'completed'; official.result = {...summarize(official.quiz.questions, official.items), confirmed: true};
      json(response, official); return;
    }
    if (url.pathname.endsWith('/events')) { json(response, {accepted: true}); return; }
    if (url.pathname.endsWith('/media')) { json(response, official); return; }
    if (url.pathname.includes('/assessments/')) { if (official) json(response, official); else response.writeHead(401).end('{}'); return; }
    response.writeHead(404).end();
  } catch (error) { response.writeHead(500).end(String(error)); }
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
async function navigate() {
  // Clear between independent fixtures after old-page pagehide has persisted its state.
  const marker = crypto.randomUUID();
  const script = await command('Page.addScriptToEvaluateOnNewDocument', {source: `localStorage.clear(); window.__fixtureNavigation = ${JSON.stringify(marker)};`});
  await command('Page.navigate', {url: `${origin}/q/${token}`});
  await until(() => evaluate(`window.__fixtureNavigation === ${JSON.stringify(marker)} && Boolean(document.querySelector("#quiz-admission"))`), 'introduction');
  await command('Page.removeScriptToEvaluateOnNewDocument', {identifier: script.identifier});
}
async function clickText(text) { await evaluate(`Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === ${JSON.stringify(text)})?.click()`); }
async function startQuiz() {
  await until(() => evaluate('Boolean(document.querySelector("#quiz-admission button:not(:disabled)"))'), 'player module ready');
  await evaluate(`{ const input = document.querySelector('[name="name"]'); if (input) input.value = 'Alex Morgan'; document.querySelector('#quiz-admission button').click(); }`);
  await until(() => evaluate('Boolean(document.querySelector(".player-question"))'), 'player');
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

  // Functional behavior only: no screenshots, viewport, or appearance assertions.
  await navigate(); await startQuiz();
  await command('Network.emulateNetworkConditions', {offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
  await evaluate('document.querySelector("input[value=paris]").click()'); await clickText('Submit answer');
  assert.equal(await evaluate('document.querySelector(".player-feedback h3").textContent'), 'Correct');
  await clickText('Next question');
  await evaluate('document.querySelector("input[value=asia]").click()');
  await until(() => evaluate('document.querySelector("#player-alert").textContent.toLowerCase().includes("connection")'), 'offline warning');
  // Reopening requires connectivity; locally confirmed q1 + q2 draft must survive.
  official.status='abandoned'; official.result={score:'0',maxScore:'3',percent:'0.00',confirmed:false};
  await command('Network.emulateNetworkConditions', {offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
  await command('Page.reload');
  await until(()=>evaluate('Boolean(document.querySelector(".player-question")) || Boolean(document.querySelector("[data-resume]")) || document.body.innerText.includes("Resume")'), 'restored run');
  await clickText('Resume quiz');
  await until(()=>evaluate('Boolean(document.querySelector("input[value=asia]:checked"))'),'restored local draft');
  await command('Network.emulateNetworkConditions', {offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
  await evaluate('document.querySelector("input[value=africa]").click()'); await clickText('Submit answer'); await clickText('Next question');
  await evaluate('{const input=document.querySelector(".player-answers input"); input.value="Tashkent"; input.dispatchEvent(new Event("input",{bubbles:true}));}');
  await clickText('Submit answer'); await clickText('See results');
  assert.equal(await evaluate('document.querySelector(".player-results-header").textContent.includes("Provisional")'),true);
  assert.equal(await evaluate('document.querySelector(".player-score").textContent'),'100.00%');
  await command('Network.emulateNetworkConditions', {offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
  await evaluate('window.dispatchEvent(new Event("online"))');
  await until(()=>evaluate('document.querySelector(".player-results-header").textContent.includes("Result confirmed")'),'recovered final result');
  assert.equal(starts,1);
  assert.equal(official.items.filter(item=>item.answerStatus==='answered').length,3);
  for (const modeValue of ['assessment','practice']) {
    mode=modeValue; feedback='after_each'; visibility=false; integrity=false; timerSeconds=600; official=null;
    const offset=requests.length;
    await navigate(); await startQuiz();
    await clickText('Skip question');
    assert.equal(await evaluate('document.querySelector(".player-feedback") === null'),true);
    await clickText('Next question'); await clickText('Skip question'); await clickText('Next question');
    await clickText('Skip question'); await clickText('See results');
    await until(()=>evaluate('Boolean(document.querySelector(".player-results-header"))'),'receipt-only result');
    assert.equal(await evaluate('document.querySelector(".player-score") === null && document.querySelector(".player-review-item") === null'),true);
    if(modeValue==='practice') assert.equal(requests.slice(offset).some(row=>/answers|finish|events/.test(row.path)),false);
    else await until(()=>official.status==='completed','receipt-only assessment sync');
  }
  mode='assessment'; feedback='after_each'; visibility=true; integrity=false; timerSeconds=null; official=null;
  await navigate(); await startQuiz();
  assert.equal(await evaluate('document.querySelector(".player-current-user").textContent.includes("Alex Morgan")'),true);
  assert.equal(await evaluate('document.querySelector(".player-timer") === null'),true);
  await evaluate('document.querySelector("input[value=paris]").click()'); await clickText('Submit answer');
  await evaluate('window.confirm=()=>true'); await clickText('Quit quiz');
  await until(()=>evaluate('document.querySelector(".player-results-header")?.textContent.includes("Quiz ended")'),'quit result');
  await until(()=>official?.status==='completed' && official?.finishReason==='quit','quit synchronization');
  assert.equal(official.items.filter(item=>item.answerStatus!=='not_reached').length,1);
  await until(()=>evaluate('Array.from(document.querySelectorAll("button")).some(button=>button.textContent.trim()==="Start again")'),'quit acknowledgement');
  await clickText('Start again');
  await until(()=>evaluate('Boolean(document.querySelector("#quiz-admission"))'),'fresh admission after quit');
  assert.deepEqual(errors,[]);
  console.log(JSON.stringify({passed:true, checks:['offline immediate feedback','persistent connection warning','reload with pending confirmation and draft','provisional abandonment reconciliation','offline results','late upload acknowledgements','receipt-only assessment/practice','practice privacy','student identity','hidden inactivity timer','quit and fresh admission'],artifacts},null,2));
} catch(error) {
  console.error(JSON.stringify({error:error.message,errors,requests:requests.slice(-12),page:socket?await evaluate('document.body.innerText').catch(()=>''):''},null,2));
  throw error;
} finally { socket?.close(); browser.kill('SIGTERM'); server.close(); }
