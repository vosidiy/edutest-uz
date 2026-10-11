// No npm dependencies. Actual PHP views/CSS/JS with a loopback fixture API, never the MAMP database.
import http from 'node:http';
import fs from 'node:fs/promises';
import path from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {summarize} from '../../public/js/player-scoring.js';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const artifacts = await fs.mkdtemp('/private/tmp/edutest-player-browser-');
const chromePath = process.env.PLAYER_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const php = process.env.PLAYER_PHP || 'php';
const token = 'a'.repeat(64);
let mode = 'assessment', feedback = 'after_each', visibility = true, integrity = true, timerSeconds = 600, official = null, forceConflict = false, loseStartResponse = false, starts = 0;
let templateFixture = false;
let quizTitle = 'A little curiosity goes a long way';
let rejectAccess = false, failSync = false, tickets = 0;
let failAdmission = false, cover = null;
let holdLoad = false, releaseLoad = null;
let changeOfficialScore = false;
const admissions = new Map();
const authoredText = '<img src=x onerror="window.__injected=true"> & "quoted" <b>plain text</b>';
const requests = [];
const quiz = () => ({title: quizTitle, description: 'Explore geography in three quick questions. Take your time, trust what you know, and learn something new.',
  teacher: 'Sarah Williams', questionCount: 3, timeLimitMinutes: timerSeconds === null ? null : String(timerSeconds / 60),
  opensAt: null, closesAt: null, passcodeRequired: false, availability: 'available', shareToken: token, emailMode: 'optional', phoneMode: 'hidden', cheatCheck: integrity, cover, mode});
const questions = () => [
  {id: '9007199254740993', type: 'single_choice', content: 'What is the capital of France?', media: null, explanation: 'Paris has been the political and cultural centre of France for centuries.', acceptedAnswers: [], correctCodes: ['paris'],
    options: [{id: '11', code: 'berlin', content: 'Berlin', media: null}, {id: '12', code: 'paris', content: 'Paris', media: null}, {id: '13', code: 'rome', content: 'Rome', media: null}, {id: '14', code: 'madrid', content: 'Madrid', media: null}]},
  {id: '2', type: 'multi_select', content: 'Which of these are continents?', media: null, explanation: 'Asia and Africa are continents. Paris is a city.', acceptedAnswers: [], correctCodes: ['asia', 'africa'],
    options: [{id: '21', code: 'asia', content: 'Asia', media: null}, {id: '22', code: 'africa', content: 'Africa', media: null}, {id: '23', code: 'city', content: 'Paris', media: null}]},
  {id: '3', type: 'short_text', content: 'What is the capital of Uzbekistan?', media: null, explanation: 'Tashkent is the capital and largest city of Uzbekistan.', acceptedAnswers: ['Tashkent', 'Toshkent'], correctCodes: [], options: []}
].map((question, index) => templateFixture ? {
  ...question,
  content: authoredText,
  explanation: authoredText,
  media: index === 0 ? {type: 'image', url: `${origin}/fixture-image`} : null,
  options: question.options.map(option => ({...option, content: authoredText})),
} : question);
const json = (response, data, status = 200) => { response.writeHead(status, {'Content-Type': 'application/json', 'Cache-Control': 'no-store'}); response.end(JSON.stringify({data, meta: {timestamp: new Date().toISOString()}})); };
let origin;
const server = http.createServer(async (request, response) => {
  try {
    const url = new URL(request.url, origin);
    if (url.pathname === '/fixture-image') {
      response.writeHead(200, {'Content-Type': 'image/png'});
      response.end(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64'));
      return;
    }
    if (/^\/(?:css|js|images|fonts)\//.test(url.pathname)) {
      const relative = url.pathname.slice(1);
      if (relative.includes('..')) { response.writeHead(404).end(); return; }
      const type = relative.endsWith('.css') ? 'text/css'
        : relative.endsWith('.js') ? 'text/javascript'
        : relative.endsWith('.woff2') ? 'font/woff2'
        : relative.endsWith('.png') ? 'image/png'
        : relative.endsWith('.jpg') || relative.endsWith('.jpeg') ? 'image/jpeg'
        : 'application/octet-stream';
      response.writeHead(200, {'Content-Type': type});
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
    if (url.pathname.includes('/assessments/') && (rejectAccess || failSync)) {
      response.writeHead(rejectAccess ? 401 : 503, {'Content-Type':'application/json'}).end(JSON.stringify({error:{code:rejectAccess ? 'invalid_credential' : 'server_error', message:'Fixture synchronization failure'}})); return;
    }
    if (url.pathname.includes('/tickets/')) {
      if (failAdmission) { response.writeHead(503, {'Content-Type':'application/json'}).end(JSON.stringify({error:{code:'server_error'}})); return; }
      json(response, {mode, ticket: `fixture-admission-${++tickets}`}); return;
    }
    if (url.pathname.endsWith('/starts')) {
      if (admissions.has(body.ticket)) { official = admissions.get(body.ticket); json(response, official); return; }
      starts++;
      const now = new Date().toISOString();
      official = {mode, attemptId: mode === 'assessment' ? `fixture-${starts}` : null, credential: 'fixture-credential', status: 'in_progress', startedAt: now,
        expiresAt: new Date(Date.now() + (timerSeconds ?? 28800) * 1000).toISOString(), deadlineReason: timerSeconds === null ? 'stale_timeout' : 'timer_expired', result: null, finishReason: null,
        ...(mode === 'assessment' ? {student:{name:body.name,email:body.email || null}} : {}),
        quiz: {...quiz(), settings: {feedback, timeLimitSec: timerSeconds, closesAt: null, ...(typeof visibility === 'boolean' ? {showScore: visibility, showAnswers: visibility, showExplain: visibility} : visibility), cheatCheck: integrity}, questions: questions()},
        items: questions().map((question, index) => ({questionId: question.id, status: index === 0 ? 'active' : 'pending', answerStatus: 'not_reached', answerCodes: [], textAnswer: ''}))};
      admissions.set(body.ticket, official);
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
      official.finishReason = body.finishReason; official.status = 'completed'; official.clientActivityAt = body.clientActivityAt;
      official.result = {...summarize(official.quiz.questions, official.items), confirmed: true, finishedAt:body.clientFinishedAt};
      if (changeOfficialScore) {
        changeOfficialScore = false;
        official.result.score = '2'; official.result.percent = '66.67';
        official.result.items[0] = {...official.result.items[0], result:'wrong', isCorrect:false};
      }
      json(response, official); return;
    }
    if (url.pathname.endsWith('/events')) { json(response, {accepted: true}); return; }
    if (url.pathname.endsWith('/media')) { json(response, official); return; }
    if (url.pathname.includes('/assessments/')) {
      if (official) {
        const saved = structuredClone(official);
        if (holdLoad) { holdLoad = false; await new Promise(resolve => { releaseLoad = resolve; }); }
        json(response, saved);
      } else response.writeHead(401).end('{}');
      return;
    }
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
async function openSharedLink() {
  const marker = crypto.randomUUID();
  const script = await command('Page.addScriptToEvaluateOnNewDocument', {source:`window.__sharedLink = ${JSON.stringify(marker)};`});
  await command('Page.navigate', {url:`${origin}/q/${token}`});
  await until(() => evaluate(`window.__sharedLink === ${JSON.stringify(marker)} && Boolean(document.querySelector('#quiz-introduction'))`), 'shared introduction loaded');
  await command('Page.removeScriptToEvaluateOnNewDocument', {identifier:script.identifier});
}
async function reloadPlayer() {
  const marker = crypto.randomUUID();
  const script = await command('Page.addScriptToEvaluateOnNewDocument', {source:`window.__reloaded = ${JSON.stringify(marker)};`});
  await command('Page.reload');
  await until(() => evaluate(`window.__reloaded === ${JSON.stringify(marker)} && Boolean(document.querySelector('#player-root'))`), 'new player document');
  await command('Page.removeScriptToEvaluateOnNewDocument', {identifier:script.identifier});
}
const savedRun = () => evaluate(`JSON.parse(localStorage.getItem('edutest:player:${token}'))`);
async function introductionReady() {
  await until(() => evaluate('!document.querySelector("#quiz-introduction").hidden && Boolean(document.querySelector("#quiz-admission button:not(:disabled)"))'), 'fresh introduction ready');
  assert.equal(await evaluate('document.querySelector("#resume-quiz") === null && document.querySelector(".player-results-header") === null'), true);
}
async function startQuiz(name = 'Alex Morgan') {
  await until(() => evaluate('Boolean(document.querySelector("#quiz-admission button:not(:disabled)"))'), 'player module ready');
  await evaluate(`{ const input = document.querySelector('[name="name"]'); if (input) input.value = ${JSON.stringify(name)}; document.querySelector('#quiz-admission button').click(); }`);
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
    else if (message.method === 'Page.javascriptDialogOpening' && message.params.type === 'beforeunload') command('Page.handleJavaScriptDialog', {accept:true}, message.sessionId);
  });
  const target = await command('Target.createTarget', {url: 'about:blank'}, null);
  sessionId = (await command('Target.attachToTarget', {targetId: target.targetId, flatten: true}, null)).sessionId;
  await command('Page.enable'); await command('Runtime.enable'); await command('Network.enable');

  // Functional behavior only: no screenshots, viewport, or appearance assertions.
  await navigate();
  assert.equal(await evaluate('document.querySelector(".player-cover").getAttribute("src")'), `${origin}/images/placeholder.jpg`);
  assert.equal(await evaluate('document.querySelector(".player-privacy").open'), false);
  assert.equal(await evaluate('document.querySelector(".player-privacy summary").textContent'), 'Privacy details');
  assert.equal(await evaluate('document.querySelector(".player-privacy").textContent.includes("IP address")'), true);
  assert.equal(await evaluate('document.querySelector(".player-privacy [data-fullscreen-button]") !== null'), true);
  assert.deepEqual(await evaluate('[...document.querySelectorAll(".player-facts dt")].map(el => el.textContent)'), ['Questions', 'Time limit', 'Quiz type']);
  assert.equal(await evaluate('document.querySelector("[data-start-label]").textContent'), 'Start new quiz');
  assert.equal(await evaluate('document.querySelector("#quiz-introduction").textContent.includes("timer continues offline")'), false);
  assert.equal(await evaluate('document.querySelector(".student-brand").textContent.trim()'), '123test');
  assert.equal(await evaluate('document.querySelector(".student-brand svg") !== null'), true);
  assert.equal(await evaluate('document.querySelector("#player-header-context").hidden && document.querySelector("#player-header-quit").hidden'), true);
  await startQuiz();
  assert.equal(await evaluate('document.querySelector("#player-header-mode").textContent'), 'Assessment');
  assert.equal(await evaluate('document.querySelector("#player-header-progress").textContent'), 'Question 1 of 3');
  assert.equal(await evaluate('!document.querySelector("#player-header-quit").hidden'), true);
  assert.equal(await evaluate('Array.from(document.querySelectorAll("button")).filter(button => button.textContent.trim() === "Quit quiz").length'), 1);
  await command('Network.emulateNetworkConditions', {offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
  await evaluate('document.querySelector("input[value=paris]").click()'); await clickText('Submit answer');
  assert.equal(await evaluate('document.querySelector(".player-feedback h3").textContent'), 'Correct');
  await clickText('Next question');
  assert.equal(await evaluate('document.querySelector("#player-header-progress").textContent'), 'Question 2 of 3');
  await evaluate('document.querySelector("input[value=asia]").click()');
  assert.equal(await evaluate('document.querySelector("#player-alert").hidden && !document.querySelector("#player-status").textContent'), true);
  // Reopening requires connectivity; locally confirmed q1 + q2 draft must survive.
  official.status='abandoned'; official.result={score:'0',maxScore:'3',percent:'0.00',confirmed:false};
  await command('Network.emulateNetworkConditions', {offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
  await reloadPlayer();
  await until(()=>evaluate('Boolean(document.querySelector(".player-question")) || Boolean(document.querySelector("[data-resume]")) || document.body.innerText.includes("Resume")'), 'restored run');
  await clickText('Resume quiz');
  await until(()=>evaluate('Boolean(document.querySelector("input[value=asia]:checked"))'),'restored local draft');
  await command('Network.emulateNetworkConditions', {offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
  await evaluate('document.querySelector("input[value=africa]").click()'); await clickText('Submit answer'); await clickText('Next question');
  await evaluate('{const input=document.querySelector(".player-answers input"); input.value="Tashkent"; input.dispatchEvent(new Event("input",{bubbles:true}));}');
  await clickText('Submit answer'); await clickText('See results');
  assert.equal(await evaluate('document.querySelector(".player-results-header").textContent.includes("Provisional")'),false);
  assert.equal(await evaluate('document.querySelector("#player-alert").hidden'), true);
  assert.equal(await evaluate('document.querySelector(".player-score").textContent'),'100.00%');
  assert.equal(await evaluate('document.querySelector(".player-completion-icon").textContent'),'🏆');
  assert.deepEqual(await evaluate('[...document.querySelectorAll(".player-result-stat strong")].map(node => node.textContent)'), ['3/3','0/3','0/3']);
  assert.equal(await evaluate('!document.querySelector("#player-header-context").hidden && document.querySelector("#player-header-progress").hidden && document.querySelector("#player-header-quit").hidden'),true);
  changeOfficialScore = true;
  await command('Network.emulateNetworkConditions', {offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
  await evaluate('window.dispatchEvent(new Event("online"))');
  await until(()=>evaluate('document.querySelector("#player-status").textContent.includes("Result saved")'),'recovered final result');
  assert.equal(await evaluate('document.querySelector(".player-score").textContent'), '66.67%');
  assert.equal(await evaluate('document.querySelector(".player-completion-icon").textContent'), '😊');
  assert.deepEqual(await evaluate('[...document.querySelectorAll(".player-result-stat strong")].map(node => node.textContent)'), ['2/3','1/3','0/3']);
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
    assert.equal(await evaluate('document.querySelector(".player-score") === null && document.querySelector(".player-result-stats") === null && document.querySelector(".player-review-item") === null'),true);
    assert.equal(await evaluate('document.querySelector(".player-completion-icon").textContent'), '🎉');
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
  assert.deepEqual(await evaluate('[...document.querySelectorAll(".player-result-stat strong")].map(node => node.textContent)'), ['1/3','0/3','2/3']);
  assert.equal(await evaluate('document.querySelector(".player-completion-icon").textContent'), '😢');
  await until(()=>evaluate('Array.from(document.querySelectorAll("button")).some(button=>button.textContent.trim()==="Start again")'),'quit acknowledgement');
  await clickText('Start again');
  await until(()=>evaluate('Boolean(document.querySelector("#quiz-admission"))'),'fresh admission after quit');

  // Template refactor: hostile-looking authored strings stay text through every render.
  templateFixture = true; mode = 'assessment'; visibility = true; feedback = 'after_each'; official = null;
  await navigate(); await startQuiz(authoredText);
  assert.equal(await evaluate('document.querySelector("#current-question").textContent'), authoredText);
  assert.equal(await evaluate('document.querySelector(".player-option-copy").textContent'), authoredText);
  assert.equal(await evaluate('document.querySelector(".player-current-user small").textContent'), authoredText);
  assert.equal(await evaluate('document.activeElement.id'), 'current-question');
  assert.equal(await evaluate('document.querySelector("#current-question img, .player-option-copy img, .player-current-user small img") === null && !window.__injected'), true);
  // Media failure renders one Retry action, and one click performs one renewal.
  await evaluate('document.querySelector(".player-media img").dispatchEvent(new Event("error")); document.querySelector(".player-media img").dispatchEvent(new Event("error"));');
  assert.equal(await evaluate('document.querySelectorAll("[data-action=retry-media]").length'), 1);
  const renewals = requests.filter(row => row.path.endsWith('/media')).length;
  await evaluate('document.querySelector("[data-action=retry-media]").click()');
  await until(() => evaluate('document.querySelector("[data-action=retry-media]") === null'), 'media retry replacement');
  assert.equal(requests.filter(row => row.path.endsWith('/media')).length, renewals + 1);

  const answerOffset = requests.length;
  await evaluate('document.querySelector("input[value=paris]").click()'); await clickText('Submit answer');
  await until(() => official.items[0].answerStatus === 'answered', 'single template submission');
  assert.equal(await evaluate('document.querySelector(".player-feedback [data-slot=explanation]").textContent'), authoredText);
  await reloadPlayer();
  await until(() => evaluate('Boolean(document.querySelector(".player-feedback"))'), 'direct play shell with templates');
  await clickText('Next question');
  assert.equal(await evaluate('document.querySelector("#player-header-progress").textContent'), 'Question 2 of 3');
  await evaluate('document.querySelector("input[value=asia]").click(); document.querySelector("input[value=africa]").click()');
  await clickText('Submit answer'); await clickText('Next question');
  await evaluate(`{ const input = document.querySelector('[data-action=type]'); input.value = ${JSON.stringify(authoredText)}; input.dispatchEvent(new Event('input', {bubbles:true})); }`);
  await clickText('Submit answer'); await clickText('See results');
  await until(() => evaluate('document.querySelector("#player-status").textContent.includes("Result saved")'), 'template result sync');
  assert.equal(requests.slice(answerOffset).filter(row => row.path.includes('/answers/')).length, 3);
  assert.equal(requests.slice(answerOffset).filter(row => row.path.endsWith('/finish')).length, 1);
  assert.equal(await evaluate('document.querySelector(".player-student-identity").textContent'), authoredText);
  assert.equal(await evaluate('document.querySelector(".player-review-item .player-feedback") === null'), true);
  await evaluate('document.querySelector(".player-review-item").open = true');
  await until(() => evaluate('Boolean(document.querySelector(".player-review-item .player-feedback"))'), 'lazy review template');
  await evaluate('document.querySelector(".player-review-item").open = false'); await pause(50);
  await evaluate('document.querySelector(".player-review-item").open = true'); await pause(50);
  assert.equal(await evaluate('document.querySelectorAll(".player-review-item .player-feedback").length'), 1);
  assert.equal(await evaluate('document.querySelectorAll(".player-review-item .player-media").length'), 1);
  assert.equal(await evaluate('document.querySelector(".player-review-item [data-slot=explanation]").textContent'), authoredText);
  assert.equal(await evaluate('!window.__injected && document.querySelector("#player-root [onerror]") === null'), true);
  await reloadPlayer();
  await until(() => evaluate('Boolean(document.querySelector(".player-results-header"))'), 'direct results shell with templates');

  // Exercise recovery components and media element contracts without visual checks.
  const contracts = await evaluate(`(async () => {
    const {createPlayerView} = await import('/js/player-view.js?v=1');
    const view = createPlayerView(key => key);
    let retries = 0;
    const blocked = view.blocked(${JSON.stringify(authoredText)}, () => retries++);
    blocked.querySelector('button').click();
    const status = view.status('pending', () => retries++);
    status.querySelector('button').click();
    const legacy = view.legacy(${JSON.stringify(authoredText)});
    const missing = view.missing(location.origin + '/q/${token}');
    const audio = view.media('audio', 'q:a', location.origin + '/fixture-audio', () => {}).querySelector('audio');
    const video = view.media('video', 'q:v', location.origin + '/fixture-video.mp4', () => {}).querySelector('video');
    const embed = view.media('embed', 'q:e', 'about:blank', () => {}).querySelector('iframe');
    return {retries, backup: legacy.querySelector('pre').textContent,
      noInjectedMarkup: legacy.querySelector('img') === null,
      missingLink: missing.querySelector('a').pathname,
      audio: audio.controls && audio.preload === 'metadata',
      video: video.controls && video.preload === 'metadata',
      embed: embed.referrerPolicy === 'strict-origin-when-cross-origin' && embed.hasAttribute('allowfullscreen')};
  })()`);
  assert.deepEqual(contracts, {retries:2, backup:authoredText, noInjectedMarkup:true, missingLink:`/q/${token}`, audio:true, video:true, embed:true});

  // At-end Practice retry mounts fresh controls once and never sends answers.
  templateFixture = false; mode = 'practice'; feedback = 'at_end'; visibility = true; official = null;
  await navigate(); await startQuiz();
  await clickText('Skip question'); await clickText('Skip question'); await clickText('Skip question');
  const beforeRestart = starts;
  await evaluate('document.querySelector("[data-try-again]").click()');
  await until(() => evaluate('Boolean(document.querySelector(".player-question"))'), 'template practice restart');
  assert.equal(starts, beforeRestart + 1);
  await clickText('Skip question');
  assert.equal(await evaluate('document.querySelector("#player-header-progress").textContent'), 'Question 2 of 3');

  // A shared link resumes only live work, without counting the navigation as activity.
  mode = 'assessment'; feedback = 'after_each'; timerSeconds = null; official = null;
  await navigate(); await startQuiz();
  const activeId = official.attemptId, activeTime = (await savedRun()).clientActivityAt;
  const activeStarts = starts;
  await openSharedLink();
  await until(() => evaluate('Boolean(document.querySelector(".player-question"))'), 'automatic live resume');
  assert.equal((await savedRun()).attemptId, activeId);
  assert.equal((await savedRun()).clientActivityAt, activeTime);
  assert.equal(starts, activeStarts);

  // A blocked second tab must not overwrite newer progress when it closes.
  const primarySession = sessionId;
  const secondary = await command('Target.createTarget', {url:'about:blank'}, null);
  const secondarySession = (await command('Target.attachToTarget', {targetId:secondary.targetId, flatten:true}, null)).sessionId;
  sessionId = secondarySession;
  await command('Page.enable'); await command('Runtime.enable');
  await openSharedLink();
  await until(() => evaluate('Boolean(document.querySelector("[data-tab-blocked]"))'), 'second tab blocked');
  assert.equal(await evaluate('document.querySelector("#quiz-introduction").hidden && document.querySelector("#player-alert").hidden && !document.querySelector("#player-status").textContent'), true);
  assert.equal(await evaluate('document.querySelector("[data-tab-blocked] h1").textContent'), quizTitle);
  sessionId = primarySession;
  await evaluate('document.querySelector("input[value=paris]").click()'); await clickText('Submit answer'); await clickText('Next question');
  await until(() => official.items[0].answerStatus === 'answered', 'primary tab progress');
  await command('Target.closeTarget', {targetId:secondary.targetId}, null);
  assert.equal((await savedRun()).index, 1);

  // Introduction uploads silently; invalid input preserves work and a valid start discards immediately.
  failSync = true;
  await evaluate('window.confirm=()=>true'); await clickText('Quit quiz');
  await until(() => evaluate('document.querySelector("#player-status").textContent.includes("Could not save result")'), 'temporary sync failure');
  await openSharedLink(); await introductionReady();
  const pendingId = (await savedRun()).attemptId;
  assert.equal(await evaluate('document.querySelector("#player-alert").hidden && !document.querySelector("#player-status").textContent'), true);
  await evaluate('document.querySelector("[name=name]").value=""; document.querySelector("#quiz-admission button").click()');
  assert.equal((await savedRun()).attemptId, pendingId); assert.equal(starts, activeStarts);
  const discardOffset = requests.length;
  await evaluate('window.confirm=()=>{throw new Error("Unexpected discard confirmation")}; localStorage.setItem("unrelated-quiz", "keep")');
  failAdmission = true;
  await evaluate('document.querySelector("[name=name]").value="New Student"; document.querySelector("#quiz-admission button").click()');
  await until(() => evaluate('document.querySelector("#player-alert").textContent.includes("Could not start")'), 'new admission failed after discard');
  assert.equal(await savedRun(), null);
  assert.equal(await evaluate('document.querySelector("[data-start-label]").textContent'), 'Start new quiz');
  assert.equal(await evaluate('document.querySelector(".player-start [aria-hidden]").textContent.trim()'), '→');
  failAdmission = false;
  await startQuiz('New Student');
  assert.equal(requests.slice(discardOffset).some(row => row.path.includes('/assessments/')), false);
  failSync = false;
  assert.notEqual(official.attemptId, pendingId);
  assert.equal(official.student.name, 'New Student');
  assert.equal((await savedRun()).index, 0);
  assert.equal(await evaluate('localStorage.getItem("unrelated-quiz")'), 'keep');
  assert.equal(await evaluate(`localStorage.getItem('edutest:player:${token}:admission')`), null);

  // Rejected old access stays silent on the introduction and needs no discard confirmation.
  rejectAccess = true;
  const rejectedAnswerOffset = requests.length;
  await clickText('Skip question');
  await until(() => requests.slice(rejectedAnswerOffset).some(row => row.path.includes('/answers/')), 'answer upload rejected during play');
  await pause(100);
  assert.equal(await evaluate('document.querySelector("#player-alert").hidden && !document.querySelector("#player-status").textContent && Boolean(document.querySelector(".player-question")) && !document.querySelector("#player-conflict").open'), true);
  await evaluate('window.confirm=()=>true'); await clickText('Quit quiz');
  await until(() => evaluate('document.querySelector("#player-status").textContent.includes("Could not save result")'), 'authorization recovery action');
  assert.equal(await evaluate('document.querySelector("#player-status button").textContent'), 'Retry');
  assert.equal(await evaluate('document.querySelector("#player-alert").hidden'), true);
  await openSharedLink(); await introductionReady();
  const rejectedId = (await savedRun()).attemptId, rejectedStarts = starts;
  assert.equal(await evaluate('document.querySelector("#player-alert").hidden && !document.querySelector("#player-status").textContent'), true);
  const rejectedOffset = requests.length;
  await evaluate('window.confirm=()=>{throw new Error("Unexpected discard confirmation")}; document.querySelector("[name=name]").value="Fresh"; document.querySelector("#quiz-admission button").click(); document.querySelector("#quiz-admission button").click()');
  await until(() => evaluate('Boolean(document.querySelector(".player-question"))'), 'fresh start without discard confirmation');
  assert.notEqual(official.attemptId, rejectedId); assert.equal(starts, rejectedStarts + 1);
  assert.equal(requests.slice(rejectedOffset).some(row => row.path.includes('/assessments/')), false);
  rejectAccess = false;

  // An inaccessible unfinished run also stays quiet; a late recovery response cannot replace a new start.
  rejectAccess = true;
  await openSharedLink(); await introductionReady();
  assert.equal(await evaluate('document.querySelector("#player-alert").hidden && !document.querySelector("#player-status").textContent'), true);
  rejectAccess = false; holdLoad = true;
  await evaluate('window.dispatchEvent(new Event("online"))');
  await until(() => Boolean(releaseLoad), 'old read-only recovery in flight');
  const previousReadId = official.attemptId;
  await startQuiz('Replacement');
  const replacementId = official.attemptId;
  assert.notEqual(replacementId, previousReadId);
  releaseLoad(); releaseLoad = null; await pause(150);
  assert.equal((await savedRun()).attemptId, replacementId);
  assert.equal(await evaluate('document.querySelector(".player-current-user").textContent.includes("Replacement")'), true);

  // Finish rejected during play remains quiet; reopening retries the saved final record silently.
  failSync = true;
  await evaluate('window.confirm=()=>true'); await clickText('Quit quiz');
  await until(() => evaluate('document.querySelector("#player-status").textContent.includes("Could not save result")'), 'pending result before shared-link recovery');
  official.status = 'abandoned';
  await openSharedLink(); await introductionReady();
  failSync = false;
  await evaluate('window.dispatchEvent(new Event("online"))');
  await until(() => official.status === 'completed', 'silent introduction final upload');
  await until(async () => (await savedRun()).result?.confirmed, 'silent confirmed persistence');
  assert.equal(await evaluate('document.querySelector("#player-alert").hidden && !document.querySelector("#player-status").textContent && document.querySelector(".player-results-header") === null'), true);
  await startQuiz();

  // Confirmed results never auto-resume when the shared link is opened.
  await evaluate('window.confirm=()=>true'); await clickText('Quit quiz');
  await until(async () => (await savedRun()).result?.confirmed, 'confirmed quit');
  await openSharedLink(); await introductionReady();
  const completedId = (await savedRun()).attemptId;
  await startQuiz(); assert.notEqual(official.attemptId, completedId);

  // Restore an expired assessment from the shared link without rendering its questions.
  timerSeconds = 1; official = null;
  await navigate(); await startQuiz();
  await evaluate('document.querySelector("input[value=paris]").click()'); // unsubmitted draft
  await command('Page.navigate', {url:'about:blank'});
  await pause(1200); official.status = 'abandoned';
  await openSharedLink(); await introductionReady();
  await until(() => official.status === 'completed', 'expired final record sync on introduction');
  assert.equal((await savedRun()).finishReason, 'timer_expired');
  assert.equal(official.items.filter(item => item.answerStatus !== 'not_reached').length, 0);
  assert.equal(await evaluate('document.querySelector(".player-question") === null'), true);
  timerSeconds = 600;

  // A lost start response retries the same admission, preserving first identity.
  official = null; await navigate(); loseStartResponse = true;
  const beforeLost = starts;
  await evaluate('document.querySelector("[name=name]").value="First identity"; document.querySelector("#quiz-admission button").click()');
  await until(() => evaluate('document.querySelector("#player-alert").textContent.includes("Could not start")'), 'lost start response');
  await startQuiz('Changed identity');
  assert.equal(starts, beforeLost + 1); assert.equal(official.student.name, 'First identity');

  // Expired Practice is not resumed; fresh starts remain anonymous and upload nothing.
  mode = 'practice'; feedback = 'at_end'; timerSeconds = 1; official = null;
  cover = {url:`${origin}/fixture-image`};
  await navigate();
  assert.equal(await evaluate('document.querySelector(".player-cover").getAttribute("src")'), cover.url);
  assert.equal(await evaluate('document.querySelector(".player-privacy") === null'), true);
  assert.equal(await evaluate('document.querySelector("#quiz-introduction").textContent.includes("Anonymous practice")'), false);
  await startQuiz(); cover = null;
  const practiceOffset = requests.length;
  await until(() => evaluate('Boolean(document.querySelector(".player-results-header"))'), 'practice expiry');
  await openSharedLink(); await introductionReady();
  timerSeconds = 600;
  const oldPractice = (await savedRun()).startedAt;
  await startQuiz(); assert.notEqual((await savedRun()).startedAt, oldPractice);
  assert.equal(requests.slice(practiceOffset).some(row => /answers|finish|events|assessments/.test(row.path)), false);

  // An explicit new start discards the previous Practice result's retry record, too.
  await clickText('Skip question'); await clickText('Skip question'); await clickText('Skip question');
  const beforePracticeLost = starts; loseStartResponse = true;
  await evaluate('document.querySelector("[data-try-again]").click()');
  await until(() => evaluate('document.querySelector("#player-alert").textContent.includes("Could not reach")'), 'lost practice restart response');
  await openSharedLink(); await introductionReady(); await startQuiz();
  assert.equal(starts, beforePracticeLost + 2);

  // A blocked introduction is exclusive; Retry recovers the latest run, never a stale Start action.
  quizTitle = authoredText;
  await navigate(); await startQuiz();
  const ownerSession = sessionId;
  const blockedTab = await command('Target.createTarget', {url:'about:blank'}, null);
  const blockedSession = (await command('Target.attachToTarget', {targetId:blockedTab.targetId, flatten:true}, null)).sessionId;
  sessionId = blockedSession;
  await command('Page.enable'); await command('Runtime.enable');
  const blockedOffset = requests.length, blockedStarts = starts;
  await openSharedLink();
  await until(() => evaluate('Boolean(document.querySelector("[data-tab-blocked]"))'), 'exclusive blocked view');
  assert.equal(await evaluate('document.querySelector("[data-tab-blocked] h1").textContent'), authoredText);
  assert.equal(await evaluate('document.querySelector("[data-tab-blocked] img, [data-tab-blocked] b") === null && !window.__injected'), true);
  assert.equal(await evaluate('document.querySelector("#quiz-introduction").hidden && document.querySelector("#player-alert").hidden && document.querySelector("#player-header-context").hidden && !document.querySelector("#player-status").textContent'), true);
  assert.deepEqual(await evaluate('[...document.querySelectorAll("#student-main button, #student-main input, #student-main a")].filter(el => !el.closest("[hidden]")).map(el => el.textContent.trim())'), ['Retry']);
  const beforeBlockedRetry = await savedRun();
  await evaluate('for (let i=0;i<3;i++) document.querySelector("[data-tab-blocked] button").click()');
  await pause(150);
  assert.deepEqual(await savedRun(), beforeBlockedRetry);
  assert.equal(starts, blockedStarts);
  assert.equal(requests.length, blockedOffset);
  assert.equal(await evaluate('Boolean(document.querySelector("[data-tab-blocked]"))'), true);

  // Advance in the owning tab, then close its document to release ownership.
  sessionId = ownerSession;
  await clickText('Skip question');
  await command('Page.navigate', {url:'about:blank'});
  sessionId = blockedSession;
  await evaluate('for (let i=0;i<3;i++) document.querySelector("[data-tab-blocked] button").click()');
  await until(() => evaluate('Boolean(document.querySelector(".player-question"))'), 'blocked retry resumes active run');
  assert.equal((await savedRun()).index, 1);
  assert.equal(await evaluate('document.querySelector("#player-header-progress").textContent'), 'Question 2 of 3');
  assert.equal(await evaluate('document.querySelector("#quiz-introduction").hidden && !document.querySelector("[data-tab-blocked]")'), true);
  assert.equal(starts, blockedStarts);

  // The old owner now blocks; when the new owner finishes, Retry restores only the introduction.
  sessionId = ownerSession;
  await openSharedLink();
  await until(() => evaluate('Boolean(document.querySelector("[data-tab-blocked]"))'), 'old owner blocked');
  sessionId = blockedSession;
  await evaluate('window.confirm=()=>true'); await clickText('Quit quiz');
  await until(() => evaluate('Boolean(document.querySelector(".player-results-header"))'), 'other tab completed');
  await command('Target.closeTarget', {targetId:blockedTab.targetId}, null);
  sessionId = ownerSession;
  await clickText('Retry'); await introductionReady();
  assert.equal(await evaluate('document.querySelector("[data-tab-blocked], .player-question, .player-results-header") === null'), true);
  assert.equal((await savedRun()).finishReason, 'quit');
  assert.equal(starts, blockedStarts);
  assert.equal(requests.slice(blockedOffset).some(row => /answers|finish|events|assessments/.test(row.path)), false);
  assert.deepEqual(errors,[]);
  console.log(JSON.stringify({passed:true, checks:['123test contextual header','offline immediate feedback','quiet offline answering','reload with pending confirmation and draft','provisional abandonment reconciliation','score summary and emoji','offline results','late upload acknowledgements','receipt-only neutral result','practice privacy','student identity','hidden inactivity timer','header Quit and fresh admission','literal authored HTML text','single submission after rerender','lazy review expansion','media retry and element contracts','recovery templates','direct play/results reload','at-end practice restart','automatic active resume without activity extension','blocked tab cannot overwrite progress','starter cover and placeholder','collapsed privacy details','invalid form preserves old work','immediate discard despite admission failure','silent inaccessible previous runs','new start cancels old uploads without confirmation','silent introduction reconciliation','single result save status','late recovery response ignored','completed/expired runs do not resume','fresh identity and unrelated storage preservation','lost new-start response deduplication'],artifacts},null,2));
} catch(error) {
  console.error(JSON.stringify({error:error.message,errors,requests:requests.slice(-12),page:socket?await evaluate('document.body.innerText').catch(()=>''):''},null,2));
  throw error;
} finally { socket?.close(); browser.kill('SIGTERM'); server.close(); }
