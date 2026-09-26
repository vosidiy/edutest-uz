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
const php = process.env.PLAYER_PHP || '/Applications/MAMP/bin/php/php8.4.17/bin/php';
const token = 'a'.repeat(64);
let mode = 'assessment', feedback = 'after_each', visibility = true, official = null, forceConflict = false, loseStartResponse = false, starts = 0;
const requests = [];
const quiz = () => ({title: 'A little curiosity goes a long way', description: 'Explore geography in three quick questions. Take your time, trust what you know, and learn something new.',
  instructions: 'Choose your answer, then submit it. You cannot return to a submitted question.', teacher: 'Sarah Williams', questionCount: 3, timeLimitSec: 600,
  opensAt: null, closesAt: null, passcodeRequired: false, availability: 'available', shareToken: token, emailMode: 'optional', phoneMode: 'hidden', cheatCheck: false, cover: null, mode});
const questions = () => [
  {id: '9007199254740993', type: 'single_choice', content: 'What is the capital of France?', points: '2.50', timeLimitSec: 120, media: null, explanation: 'Paris has been the political and cultural centre of France for centuries.', acceptedAnswers: [], correctCodes: ['paris'],
    options: [{id: '11', code: 'berlin', content: 'Berlin', media: null}, {id: '12', code: 'paris', content: 'Paris', media: null}, {id: '13', code: 'rome', content: 'Rome', media: null}, {id: '14', code: 'madrid', content: 'Madrid', media: null}]},
  {id: '2', type: 'multi_select', content: 'Which of these are continents?', points: '2.00', timeLimitSec: null, media: null, explanation: 'Asia and Africa are continents. Paris is a city.', acceptedAnswers: [], correctCodes: ['asia', 'africa'],
    options: [{id: '21', code: 'asia', content: 'Asia', media: null}, {id: '22', code: 'africa', content: 'Africa', media: null}, {id: '23', code: 'city', content: 'Paris', media: null}]},
  {id: '3', type: 'short_text', content: 'What is the capital of Uzbekistan?', points: '1.50', timeLimitSec: null, media: null, explanation: 'Tashkent is the capital and largest city of Uzbekistan.', acceptedAnswers: ['Tashkent', 'Toshkent'], correctCodes: [], options: []}
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
      official = {mode, attemptId: mode === 'assessment' ? 'fixture' : null, credential: 'fixture-credential', version: 1, status: 'in_progress', phase: 'answering', startedAt: now,
        totalDueAt: new Date(Date.now() + 600000).toISOString(), closeAt: null, result: null, finishReason: null,
        quiz: {...quiz(), settings: {feedback, ...(typeof visibility === 'boolean' ? {showScore: visibility, showAnswers: visibility, showExplain: visibility} : visibility), cheatCheck: false}, questions: questions()},
        items: questions().map((question, index) => ({questionId: question.id, status: index === 0 ? 'active' : 'pending', saveVer: 0, answerCodes: [], textAnswer: '', submitKey: null, reason: null, startedAt: index === 0 ? now : null}))};
      if (loseStartResponse) { loseStartResponse = false; response.writeHead(200, {'Content-Type': 'application/json'}).end('{"data":'); return; }
      json(response, official); return;
    }
    if (url.pathname.endsWith('/sync')) {
      if (forceConflict) {
        forceConflict = false; official.version++;
        Object.assign(official.items[0], {status: 'locked', answerCodes: ['berlin'], submitKey: 'b'.repeat(32), reason: 'answered', saveVer: 2});
        response.writeHead(409, {'Content-Type': 'application/json'}).end(JSON.stringify({error: {code: 'progress_conflict', message: 'Conflict'}, meta: {timestamp: new Date().toISOString()}})); return;
      }
      for (const item of body.items) Object.assign(official.items.find(row => row.questionId === item.questionId), item, {status: item.submitKey ? 'locked' : 'active'});
      official.version++;
      if (body.finishReason) { official.finishReason = body.finishReason; official.status = 'submitted'; official.phase = 'complete'; official.result = {...summarize(official.quiz.questions, official.items), confirmed: true, lateSync: false}; }
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
async function screenshot(name) {
  const result = await command('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
  await fs.writeFile(path.join(artifacts, name + '.png'), Buffer.from(result.data, 'base64'));
}
async function viewport(width, height) { await command('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 600}); }
async function navigate() {
  // Clear between independent fixtures after old-page pagehide has persisted its state.
  const marker = crypto.randomUUID();
  const script = await command('Page.addScriptToEvaluateOnNewDocument', {source: `sessionStorage.clear(); window.__fixtureNavigation = ${JSON.stringify(marker)};`});
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
  await viewport(1280, 1000); await navigate(); await screenshot('intro-desktop');
  await startQuiz(); await screenshot('question-desktop');
  await command('Input.dispatchKeyEvent', {type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9});
  await command('Input.dispatchKeyEvent', {type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9});
  assert.equal(await evaluate('document.activeElement.tagName'), 'INPUT');
  for (const [width, height, label] of [[768, 1024, 'tablet'], [390, 844, 'mobile']]) {
    await viewport(width, height); assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, `${label} overflow`); await screenshot('question-' + label);
  }
  await command('Emulation.setEmulatedMedia', {features: [{name: 'prefers-reduced-motion', value: 'reduce'}]});
  assert.equal(await evaluate('getComputedStyle(document.querySelector(".player-option")).transitionDuration'), '0s');
  await command('Network.emulateNetworkConditions', {offline: true, latency: 0, downloadThroughput: 0, uploadThroughput: 0});
  await evaluate('document.querySelector("input[value=paris]").click()'); await clickText('Submit answer');
  assert.equal(await evaluate('document.querySelector(".player-feedback h3").textContent'), 'Correct'); await screenshot('feedback-mobile');
  await clickText('Next question'); await evaluate('document.querySelector("input[value=asia]").click(); document.querySelector("input[value=africa]").click()'); await clickText('Submit answer'); await clickText('Next question');
  await evaluate('{ const input = document.querySelector(".player-answers input"); input.value = "Tashkent"; input.dispatchEvent(new Event("input", {bubbles:true})); }'); await clickText('Submit answer'); await clickText('See results');
  assert.equal(await evaluate('document.querySelector(".player-results-header").textContent.includes("Provisional")'), true);
  await command('Network.emulateNetworkConditions', {offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1});
  await evaluate('window.dispatchEvent(new Event("online"))');
  await until(() => evaluate('document.querySelector(".player-results-header").textContent.includes("Result confirmed")'), 'online confirmation');
  assert.equal(await evaluate('document.querySelector(".player-score").textContent'), '100.00%'); await screenshot('results-mobile');
  assert.equal(starts, 1);
  // Anonymous at-end mode with every result-visibility toggle off.
  await evaluate('sessionStorage.clear()'); official = null; mode = 'practice'; feedback = 'at_end'; visibility = false;
  const requestIndex = requests.length;
  await navigate(); await startQuiz();
  await clickText('Skip question'); assert.equal(await evaluate('document.querySelector(".player-feedback") === null'), true);
  await clickText('Skip question'); await clickText('Skip question');
  assert.equal(await evaluate('document.querySelector(".player-score") === null'), true);
  await evaluate('document.querySelector(".player-review-item").open = true'); await pause(100);
  assert.equal(await evaluate('document.querySelector(".player-feedback").textContent.includes("Correct answer")'), false);
  assert.equal(requests.slice(requestIndex).some(request => /sync|events|results/.test(request.path)), false);
  // Real conflict UI and focus trap.
  await evaluate('sessionStorage.clear()'); official = null; mode = 'assessment'; feedback = 'after_each'; visibility = true;
  await navigate(); await startQuiz(); forceConflict = true;
  await evaluate('document.querySelector("input[value=paris]").click()'); await clickText('Submit answer');
  await until(() => evaluate('document.querySelector("#player-conflict").open'), 'conflict dialog'); await screenshot('conflict-mobile');
  assert.equal(await evaluate('document.querySelector("#player-conflict").contains(document.activeElement)'), true);
  await evaluate('document.querySelector("#use-server").click()');
  await until(() => evaluate('!document.querySelector("#player-conflict").open'), 'resolved conflict');
  assert.equal(await evaluate('document.querySelector("input[value=berlin]").checked'), true);
  // Reload preserves a locked answer; no new start or backward editing.
  const previousStarts = starts;
  await command('Page.reload');
  await until(() => evaluate('Boolean(document.querySelector(".player-question fieldset:disabled"))'), 'refresh recovery');
  assert.equal(starts, previousStarts);
  // Every valid feedback/visibility combination (explanations require answer visibility).
  await evaluate('sessionStorage.clear()'); mode = 'practice';
  for (const timing of ['after_each', 'at_end']) for (const showScore of [false, true]) for (const [showAnswers, showExplain] of [[false, false], [true, false], [true, true]]) {
    feedback = timing; visibility = {showScore, showAnswers, showExplain}; official = null;
    await navigate(); await startQuiz(); await evaluate('document.querySelector("input[value=paris]").click()'); await clickText('Submit answer');
    if (timing === 'after_each') {
      assert.equal(await evaluate('document.querySelector(".player-feedback").textContent.includes("2.50 / 2.50")'), showScore);
      assert.equal(await evaluate('document.querySelector(".player-feedback").textContent.includes("Correct answer")'), showAnswers);
      assert.equal(await evaluate('document.querySelector(".player-feedback").textContent.includes("Explanation")'), showExplain);
      await clickText('Next question');
    } else assert.equal(await evaluate('document.querySelector(".player-feedback") === null'), true);
    await clickText('Skip question'); if (timing === 'after_each') await clickText('Next question');
    await clickText('Skip question'); if (timing === 'after_each') await clickText('See results');
    assert.equal(await evaluate('Boolean(document.querySelector(".player-score"))'), showScore);
    await evaluate('document.querySelector(".player-review-item").open = true');
    await until(() => evaluate('Boolean(document.querySelector(".player-feedback"))'), 'result review');
    assert.equal(await evaluate('document.querySelector(".player-feedback").textContent.includes("Correct answer")'), showAnswers);
    assert.equal(await evaluate('document.querySelector(".player-feedback").textContent.includes("Explanation")'), showExplain);
    await evaluate('sessionStorage.clear()');
  }
  // A lost start acknowledgement recovers using the pre-stored bearer, not another start.
  mode = 'assessment'; feedback = 'after_each'; visibility = true; official = null; loseStartResponse = true;
  await navigate();
  await until(() => evaluate('Boolean(document.querySelector("#quiz-admission button:not(:disabled)"))'), 'admission ready');
  await evaluate('document.querySelector("[name=name]").value = "Alex Morgan"; document.querySelector("#quiz-admission button").click()');
  await until(() => evaluate('!document.querySelector("#player-alert").hidden'), 'lost start error');
  const acceptedStarts = starts;
  await startQuiz(); assert.equal(starts, acceptedStarts);
  // No storage: continue in memory and explain the loss-of-recovery risk.
  await evaluate('sessionStorage.clear()'); mode = 'practice'; official = null;
  const storageScript = await command('Page.addScriptToEvaluateOnNewDocument', {source: 'Storage.prototype.setItem = function () { throw new DOMException("Fixture quota", "QuotaExceededError"); };'});
  await navigate(); await startQuiz();
  assert.equal(await evaluate('document.querySelector("#player-alert").textContent.includes("storage")'), true);
  await clickText('Skip question'); await clickText('Next question');
  assert.equal(await evaluate('document.querySelector(".player-question-number").textContent'), 'Question 2 of 3');
  await command('Page.removeScriptToEvaluateOnNewDocument', {identifier: storageScript.identifier});
  assert.deepEqual(errors, []);
  console.log(JSON.stringify({passed: true, widths: [1280, 768, 390], checks: ['actual PHP views', 'keyboard focus', 'reduced motion', 'offline completion', 'server confirmation', 'anonymous practice', '12 visibility combinations', 'conflict dialog', 'refresh recovery', 'lost start acknowledgement', 'storage unavailable'], artifacts}, null, 2));
} catch (error) {
  console.error(JSON.stringify({error: error.message, errors, networkFailures, requests: requests.slice(-12), artifacts, page: socket ? await evaluate('({text:document.body.innerText, scripts:[...document.scripts].map(s=>s.src)})').catch(() => '') : ''}, null, 2));
  if (socket) await screenshot('failure').catch(() => {});
  throw error;
} finally {
  socket?.close(); browser.kill('SIGTERM'); server.close();
  // The unique temporary profile and screenshots are retained for inspection.
}
