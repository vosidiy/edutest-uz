// Functional browser coverage only: no screenshots, viewport changes, or visual assertions.
import http from 'node:http';
import fs from 'node:fs/promises';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {launchBrowser} from './browser-harness.mjs';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const php = process.env.PLAYER_PHP || 'php';
const shareToken = '123456789';
const requests = [];
let origin;
let official = null;

const questions = [
  {id:'1',content:'First question?',optionsLayout:'list',media:null,explanation:'Right is correct.',correctCode:'right',options:[
    {id:'11',code:'right',content:'Right',media:null},{id:'12',code:'wrong',content:'Wrong',media:null},
  ]},
  {id:'2',content:'Second question?',optionsLayout:'grid',media:null,explanation:'Right is correct.',correctCode:'right',options:[
    {id:'21',code:'wrong',content:'Wrong',media:null},{id:'22',code:'right',content:'Right',media:null},
  ]},
];
const snapshot = () => ({schemaVersion:1,quiz:{title:'Snapshot quiz',description:'Two questions',shareToken,timeLimitSec:7200,
  askEmail:false,askPhone:false,feedback:'after_each',showScore:true,showAnswers:true,showExplain:true},cover:null,questions});
const summary = () => ({title:'Snapshot quiz',description:'Two questions',teacher:'Teacher',questionCount:2,timeLimitMinutes:'120.0',
  shareToken,askEmail:false,askPhone:false,cover:null});
const iso = () => new Date().toISOString();
const ack = () => ({attemptId:official.attemptId,status:official.status,answerVersion:official.answerVersion,
  lastSavedAt:official.lastSavedAt,expiresAt:official.expiresAt,finishedAt:official.finishedAt,
  finishReason:official.finishReason,lateSync:official.lateSync});
const sendJson = (response, data, status = 200) => {
  response.writeHead(status, {'Content-Type':'application/json','Cache-Control':'no-store'});
  response.end(JSON.stringify(status < 400 ? {data,meta:{timestamp:iso()}} : {error:data,meta:{timestamp:iso()}}));
};

const server = http.createServer(async (request, response) => {
  try {
    const url = new URL(request.url, origin);
    if (/^\/(?:css|js|images|fonts)\//.test(url.pathname)) {
      const relative = url.pathname.slice(1);
      if (relative.includes('..')) { response.writeHead(404).end(); return; }
      const type = relative.endsWith('.css') ? 'text/css' : relative.endsWith('.js') ? 'text/javascript'
        : relative.endsWith('.woff2') ? 'font/woff2' : 'image/png';
      response.writeHead(200, {'Content-Type':type});
      response.end(await fs.readFile(path.join(repo, 'public', relative)));
      return;
    }
    if (url.pathname === '/favicon.ico') { response.writeHead(204).end(); return; }
    if (url.pathname.startsWith(`/q/${shareToken}`)) {
      const page = url.pathname.endsWith('/start') ? 'start' : url.pathname.endsWith('/results') ? 'results' : 'intro';
      const html = execFileSync(php, [path.join(repo, 'tests/browser/render.php'), origin + '/'], {
        cwd:repo,input:JSON.stringify({page,quiz:summary()}),maxBuffer:2 * 1024 * 1024,
      });
      response.writeHead(200, {'Content-Type':'text/html','Cache-Control':'no-store'});
      response.end(html);
      return;
    }

    requests.push({method:request.method,path:url.pathname});
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    const body = chunks.length ? JSON.parse(Buffer.concat(chunks).toString()) : {};

    if (url.pathname === `/api/v1/player/tickets/${shareToken}`) {
      sendJson(response, {ticket:'fixture-ticket',quizVersion:1}); return;
    }
    if (url.pathname === '/api/v1/player/starts') {
      const now = new Date();
      official = {attemptId:'a'.repeat(32),credential:'b'.repeat(64),status:'in_progress',answerVersion:0,answers:[],
        startedAt:now.toISOString(),lastSavedAt:now.toISOString(),expiresAt:new Date(now.getTime() + 7200_000).toISOString(),
        finishedAt:null,finishReason:null,lateSync:false,student:{name:body.name,email:null,phone:null},quiz:snapshot(),result:null};
      sendJson(response, official); return;
    }
    if (url.pathname.endsWith('/progress')) {
      official.answerVersion = body.answerVersion;
      official.answers = structuredClone(body.answers);
      official.lastSavedAt = iso();
      sendJson(response, ack()); return;
    }
    if (url.pathname.endsWith('/finish')) {
      official.answerVersion = body.answerVersion;
      official.answers = structuredClone(body.answers);
      official.status = 'completed';
      official.finishReason = body.finishReason;
      official.finishedAt = body.clientFinishedAt;
      official.lastSavedAt = iso();
      const items = questions.map((question, index) => {
        const answer = official.answers[index];
        return {questionId:question.id,answerCode:answer.answerCode,correctCode:question.correctCode,
          result:answer.answerCode === null ? 'skipped' : answer.answerCode === question.correctCode ? 'correct' : 'wrong'};
      });
      const score = items.filter(item => item.result === 'correct').length;
      official.result = {official:true,provisional:false,finishedAt:official.finishedAt,finishReason:official.finishReason,
        lateSync:false,score,maxScore:questions.length,percent:(score * 100 / questions.length).toFixed(2),items};
      sendJson(response, official); return;
    }
    if (url.pathname.endsWith('/media')) { sendJson(response, official); return; }
    if (url.pathname.includes('/assessments/')) { sendJson(response, official); return; }
    response.writeHead(404).end();
  } catch (error) {
    response.writeHead(500, {'Content-Type':'text/plain'}).end(String(error.stack || error));
  }
});

await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
origin = `http://127.0.0.1:${server.address().port}`;
const browser = await launchBrowser();

try {
  // Direct player access has no local attempt and returns to the introduction.
  await browser.navigate(`${origin}/q/${shareToken}/start`);
  await browser.until(() => browser.evaluate(`location.pathname === '/q/${shareToken}' && !!document.querySelector('#quiz-admission')`), 'direct start redirect');
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Practice')`), false);
  assert.deepEqual(await browser.evaluate(`[...document.querySelectorAll('.player-facts dt')].map(node => node.textContent)`), ['Questions','Time limit','Format']);

  await browser.evaluate(`{
    document.querySelector('[name="name"]').value = 'Offline Student';
    document.querySelector('#quiz-admission button[type="submit"]').click();
  }`);
  await browser.until(() => browser.evaluate(`location.pathname.endsWith('/start') && !!document.querySelector('.player-question')`), 'attempt start');
  assert.equal(await browser.evaluate(`JSON.parse(localStorage.getItem('edutest:player:${shareToken}')).format`), 5);
  assert.equal(await browser.evaluate(`document.querySelector('.player-answers').classList.contains('player-answers-grid')`), false);
  assert.ok(await browser.evaluate(`{
    const [minutes,seconds] = document.querySelector('[data-slot="remaining"]').textContent.split(':').map(Number);
    minutes * 60 + seconds >= 7190;
  }`));

  await browser.evaluate(`document.querySelector('input[value="right"]').click();document.querySelector('[data-action="submit"]').click()`);
  await browser.until(() => browser.evaluate(`!!document.querySelector('.player-feedback')`), 'first feedback');
  await browser.until(() => requests.some(row => row.path.endsWith('/progress')), 'first checkpoint');
  await browser.evaluate(`document.querySelector('[data-action="next"]').click()`);
  await browser.until(() => browser.evaluate(`document.querySelector('#current-question').textContent === 'Second question?'`), 'second question');
  assert.equal(await browser.evaluate(`document.querySelector('.player-answers').classList.contains('player-answers-grid')`), true);

  // Reload through the introduction: active local state redirects back to the player.
  await browser.navigate(`${origin}/q/${shareToken}`);
  await browser.until(() => browser.evaluate(`location.pathname.endsWith('/start') && document.querySelector('#current-question')?.textContent === 'Second question?'`), 'active introduction redirect');

  await browser.command('Network.emulateNetworkConditions', {offline:true,latency:0,downloadThroughput:0,uploadThroughput:0,connectionType:'none'});
  await browser.evaluate(`document.querySelector('input[value="wrong"]').click();document.querySelector('[data-action="submit"]').click()`);
  await browser.evaluate(`document.querySelector('[data-action="next"]').click()`);
  await browser.until(() => browser.evaluate(`!!document.querySelector('.player-results-header')`), 'offline result');
  assert.equal(await browser.evaluate(`JSON.parse(localStorage.getItem('edutest:player:${shareToken}')).finishPending`), true);
  assert.equal(await browser.evaluate(`document.querySelector('[data-slot="status"]').textContent.includes('Provisional')`), true);

  await browser.command('Network.emulateNetworkConditions', {offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1,connectionType:'wifi'});
  await browser.evaluate(`window.dispatchEvent(new Event('online'))`);
  await browser.until(() => official?.status === 'completed', 'official finish upload');
  await browser.until(() => browser.evaluate(`JSON.parse(localStorage.getItem('edutest:player:${shareToken}')).result?.official === true`), 'official reconciliation');
  assert.equal(requests.filter(row => row.path.endsWith('/finish')).length, 1);
  assert.equal(official.answers.length, 2);
  assert.equal(await browser.evaluate(`document.querySelector('[data-slot="status"]').textContent.includes('confirmed')`), true);
  assert.deepEqual(browser.errors, []);
  console.log('Player snapshot/offline browser behavior passed.');
} catch (error) {
  const page = await browser.evaluate(`({path:location.pathname,text:document.body.innerText.slice(0,1000),alert:document.querySelector('#player-alert')?.textContent})`).catch(() => null);
  console.error(JSON.stringify({message:error.message,page,requests,browserErrors:browser.errors}, null, 2));
  throw error;
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
