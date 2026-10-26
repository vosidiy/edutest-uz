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
const publicId = 'a'.repeat(32);
const user = {id:1,email:'teacher@example.test',display_name:'Teacher One',phone:null,bio:'',timezone:'Asia/Tashkent',public_page:false};
const quiz = {publicId,shareUrl:'/q/123456789',resultsAvailable:true,resultsUrl:`/results/quizzes/${publicId}`,version:4,
  status:'published',deleted:false,listed:false,title:'Snapshot assessment',description:'Current authoring pool',cover:null,
  timeLimitSec:7200,timeLimitMinutes:'120.0',askEmail:true,askPhone:false,feedback:'at_end',shuffleQuestions:true,
  shuffleOptions:false,drawCount:1,showScore:true,showAnswers:true,showExplain:true,publishedAt:new Date().toISOString(),
  updatedAt:new Date().toISOString(),mediaLimits:{imageBytes:5242880,audioBytes:20971520,serverBytes:20971520},questions:[{
    id:'1',position:1,content:'Which answer is correct?',optionsLayout:'grid',explanation:'A is correct.',media:null,
    options:[{id:'11',code:'code-a',position:1,content:'A',isCorrect:true,media:null},{id:'12',code:'code-b',position:2,content:'B',isCorrect:false,media:null}],
  }]};
const library = {rows:[{publicId,title:quiz.title,status:'published',deleted:false,questionCount:1,cover:null,
  assessmentSubmissions:3,updatedAt:'26 Oct 2026, 12:00',editUrl:`/quizzes/${publicId}/edit`,resultsUrl:quiz.resultsUrl}],
  filters:{status:'',sort:'updated_desc',page:1},pagination:{page:1,pageSize:20,total:1,pageCount:1}};
const report = {quiz:{publicId,title:quiz.title,builderUrl:`/quizzes/${publicId}/edit`,restoreUrl:'/dashboard?status=trash',status:'published'},
  metrics:{finalizedAttempts:3,inProgressAttempts:1,averagePercent:'75.00'},attempts:[],
  filters:{query:'',status:'all',dateFrom:null,dateTo:null,minScore:null,maxScore:null,sort:'newest',page:1},
  pagination:{page:1,pageSize:25,total:0,pageCount:1},exportUrl:`/results/quizzes/${publicId}/export.csv`};
let origin;

function render(page, data) {
  return execFileSync(php, [path.join(repo, 'tests/browser/render-teacher.php'), origin + '/'], {
    cwd:repo,input:JSON.stringify({page,data}),maxBuffer:3 * 1024 * 1024,
  });
}

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
    if (url.pathname === `/api/v1/quizzes/${publicId}`) {
      response.writeHead(200, {'Content-Type':'application/json','Cache-Control':'no-store'});
      response.end(JSON.stringify({data:{quiz},meta:{timestamp:new Date().toISOString()}}));
      return;
    }
    let html;
    if (url.pathname === '/dashboard') html = render('dashboard', {title:'Dashboard — EduTest',quizWorkspace:false,builderHeader:false,user,library,
      paginationNavigation:{pageCount:1,previousUrl:null,nextUrl:null},dateLabel:'Monday, 26 October'});
    else if (url.pathname === `/quizzes/${publicId}/edit`) html = render('builder', {title:'Quiz builder — EduTest',quizWorkspace:true,builderHeader:true,user,quiz});
    else if (url.pathname === `/results/quizzes/${publicId}`) html = render('responses', {title:'Responses — EduTest',quizWorkspace:true,builderHeader:false,user,report});
    else { response.writeHead(404).end(); return; }
    response.writeHead(200, {'Content-Type':'text/html','Cache-Control':'no-store'});
    response.end(html);
  } catch (error) {
    response.writeHead(500, {'Content-Type':'text/plain'}).end(String(error.stack || error));
  }
});

await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
origin = `http://127.0.0.1:${server.address().port}`;
const browser = await launchBrowser();

try {
  await browser.navigate(`${origin}/dashboard`);
  await browser.until(() => browser.evaluate(`!!document.querySelector('.quiz-card')`), 'dashboard card');
  assert.equal(await browser.evaluate(`document.querySelector('.quiz-card').textContent.includes('Assessment')`), true);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Practice')`), false);
  assert.deepEqual(await browser.evaluate(`[...document.querySelector('select[name="status"]').options].map(option => option.value)`), ['', 'draft', 'published', 'trash']);
  await browser.evaluate(`document.querySelector('[data-open-create]').click()`);
  await browser.until(() => browser.evaluate(`document.querySelector('#create-quiz-dialog').open`), 'create dialog');
  assert.equal(await browser.evaluate(`document.querySelector('#create-quiz-dialog').textContent.includes('Quiz mode')`), false);

  await browser.navigate(`${origin}/quizzes/${publicId}/edit`);
  await browser.until(() => browser.evaluate(`document.querySelector('#quiz-builder')?.hasAttribute('v-cloak') === false && document.body.textContent.includes('Which answer is correct?')`), 'builder hydration');
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Unpublish')`), true);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Single choice · grid')`), true);
  for (const removed of ['Practice','Passcode','Integrity','Short text','Multiple choice']) {
    assert.equal(await browser.evaluate(`document.body.textContent.includes(${JSON.stringify(removed)})`), false, removed);
  }
  await browser.evaluate(`document.querySelector('[aria-controls="builder-settings"]').click()`);
  assert.equal(await browser.evaluate(`document.querySelector('.builder-layout').dataset.activePanel`), 'settings');
  assert.equal(await browser.evaluate(`document.querySelector('#builder-settings').textContent.includes('Questions per attempt')`), true);
  assert.equal(await browser.evaluate(`document.querySelector('#builder-settings').textContent.includes('7200 seconds')`), true);

  await browser.navigate(`${origin}/results/quizzes/${publicId}`);
  await browser.until(() => browser.evaluate(`document.body.textContent.includes('immutable snapshot')`), 'snapshot report');
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Paper revision')`), false);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Integrity timeline')`), false);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Completed or provisionally expired')`), true);
  assert.deepEqual(browser.errors, []);
  console.log('Teacher assessment-only workspace browser behavior passed.');
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
