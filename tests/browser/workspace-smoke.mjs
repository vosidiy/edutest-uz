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
const newPublicId = 'b'.repeat(32);
const user = {id:1,email:'teacher@example.test',display_name:'Teacher One',username:null,phone:null,bio:'',timezone:'Asia/Tashkent',public_page:false};
const quiz = {publicId,shareUrl:'/q/123456789',resultsAvailable:true,resultsUrl:`/results/quizzes/${publicId}`,
  status:'published',deleted:false,title:'Snapshot assessment',description:'Current authoring pool',cover:null,
  timeLimitSec:7200,timeLimitMinutes:120,askEmail:true,askPhone:false,feedback:'at_end',shuffleQuestions:true,
  shuffleOptions:false,drawCount:1,showScore:true,showAnswers:true,showExplain:true,publishedAt:new Date().toISOString(),
  updatedAt:new Date().toISOString(),mediaLimits:{imageBytes:5242880,audioBytes:20971520,serverBytes:20971520},questions:[{
    id:'1',position:1,content:'Which answer is correct?',optionsLayout:'grid',explanation:'A is correct.',media:null,
    options:[{id:'11',code:'code-a',position:1,content:'A',isCorrect:true,media:null},{id:'12',code:'code-b',position:2,content:'B',isCorrect:false,media:null}],
  }]};
const newQuiz = {...quiz,publicId:newPublicId,shareUrl:'/q/987654321',resultsAvailable:false,resultsUrl:null,
  status:'draft',title:'Active by default',description:'',publishedAt:null,questions:[]};
const library = {rows:[{publicId,title:quiz.title,status:'published',questionCount:1,cover:null,
  assessmentSubmissions:3,updatedAt:'26 Oct 2026, 12:00',editUrl:`/quizzes/${publicId}/edit`,resultsUrl:quiz.resultsUrl}],
  filters:{page:1},pagination:{page:1,pageSize:20,total:1,pageCount:1}};
const report = {quiz:{publicId,title:quiz.title,builderUrl:`/quizzes/${publicId}/edit`,status:'published'},
  attempts:[],filters:{query:'Ali',dateRange:'last_7_days',dateFromUtc:null,dateToUtc:null,minScore:50,page:1},
  pagination:{page:1,pageSize:25,total:26,pageCount:2}};
let origin;
let quizPutCount = 0;
let newQuizPutCount = 0;

async function requestJson(request) {
  const chunks = [];
  for await (const chunk of request) chunks.push(chunk);
  return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

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
    if (url.pathname === '/api/v1/account/public-page/availability' && request.method === 'GET') {
      const username = (url.searchParams.get('username') || '').trim().toLowerCase();
      const valid = /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(username) && username.length >= 3 && username.length <= 30;
      response.writeHead(200, {'Content-Type':'application/json','Cache-Control':'no-store'});
      response.end(JSON.stringify({data:{username,valid,available:valid},meta:{timestamp:new Date().toISOString()}}));
      return;
    }
    if (url.pathname === '/api/v1/account/public-page' && request.method === 'PUT') {
      const saved = await requestJson(request);
      Object.assign(user, {username:saved.username.toLowerCase(),bio:saved.bio,public_page:!!saved.publicPage});
      response.writeHead(200, {'Content-Type':'application/json','Cache-Control':'no-store'});
      response.end(JSON.stringify({data:{username:user.username,bio:user.bio,publicPage:user.public_page,url:`/user/${user.username}`},meta:{timestamp:new Date().toISOString()}}));
      return;
    }
    if (url.pathname === '/api/v1/quizzes' && request.method === 'POST') {
      const created = await requestJson(request);
      newQuiz.title = created.title;
      response.writeHead(201, {'Content-Type':'application/json','Cache-Control':'no-store'});
      response.end(JSON.stringify({data:{publicId:newPublicId,editUrl:`/quizzes/${newPublicId}/edit`},meta:{timestamp:new Date().toISOString()}}));
      return;
    }
    if ([`/api/v1/quizzes/${publicId}`, `/api/v1/quizzes/${newPublicId}`].includes(url.pathname)) {
      const target = url.pathname.endsWith(newPublicId) ? newQuiz : quiz;
      if (request.method === 'PUT') {
        const saved = await requestJson(request);
        if (target === newQuiz) newQuizPutCount++;
        else quizPutCount++;
        if (target === newQuiz && saved.status === 'published' && saved.questions.length === 0) {
          response.writeHead(422, {'Content-Type':'application/json','Cache-Control':'no-store'});
          response.end(JSON.stringify({error:{code:'not_publishable',message:'Add at least one question before making this quiz Active.',fields:{}},meta:{timestamp:new Date().toISOString()}}));
          return;
        }
        if (target === newQuiz) saved.questions = saved.questions.map((question, questionIndex) => ({...question,id:String(100 + questionIndex),
          options:question.options.map((option, optionIndex) => ({...option,id:String(1000 + optionIndex),code:`new-${optionIndex}`,
            content:option.content || `Option ${optionIndex + 1}`}))}));
        Object.assign(target, saved, {publishedAt:saved.status === 'published' ? (target.publishedAt || new Date().toISOString()) : target.publishedAt,
          updatedAt:new Date().toISOString()});
      }
      response.writeHead(200, {'Content-Type':'application/json','Cache-Control':'no-store'});
      response.end(JSON.stringify({data:{quiz:target},meta:{timestamp:new Date().toISOString()}}));
      return;
    }
    let html;
    if (url.pathname === '/dashboard') html = render('dashboard', {title:'Dashboard — EduTest',quizWorkspace:false,builderHeader:false,user,library,
      paginationNavigation:{pageCount:1,previousUrl:null,nextUrl:null},dateLabel:'Monday, 26 October'});
    else if (url.pathname === `/quizzes/${publicId}/edit`) html = render('builder', {title:'Quiz builder — EduTest',quizWorkspace:true,builderHeader:true,user,quiz});
    else if (url.pathname === `/quizzes/${newPublicId}/edit`) html = render('builder', {title:'Quiz builder — EduTest',quizWorkspace:true,builderHeader:true,user,quiz:newQuiz});
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
  assert.equal(await browser.evaluate(`document.querySelector('.library-toolbar') === null`), true);
  assert.equal(await browser.evaluate(`document.querySelector('[name="status"], [name="sort"]') === null`), true);
  assert.equal(await browser.evaluate(`document.querySelector('[data-quiz-action="publish"], [data-quiz-action="unpublish"]') === null`), true);
  await browser.evaluate(`document.querySelector('[data-open-public-page]').click()`);
  await browser.until(() => browser.evaluate(`document.querySelector('#public-page-dialog').open`), 'public page dialog');
  await browser.evaluate(`(() => { const input=document.querySelector('#dialog_public_username');input.value='teacher-one';input.dispatchEvent(new Event('input',{bubbles:true})); })()`);
  await browser.until(() => browser.evaluate(`document.querySelector('#public-page-dialog [data-username-status]').dataset.state === 'available'`), 'username availability');
  await browser.evaluate(`(() => { document.querySelector('#dialog_public_bio').value='Teacher bio';document.querySelector('#public-page-dialog [data-public-page-submit]').click(); })()`);
  await browser.until(() => browser.evaluate(`!document.querySelector('#public-page-dialog').open && document.querySelector('[data-public-page-action] a')?.textContent.trim() === 'View my page'`), 'public page creation');
  assert.equal(await browser.evaluate(`document.querySelector('[data-public-page-action] a').getAttribute('href')`), '/user/teacher-one');
  await browser.evaluate(`document.querySelector('[data-open-create]').click()`);
  await browser.until(() => browser.evaluate(`document.querySelector('#create-quiz-dialog').open`), 'create dialog');
  assert.equal(await browser.evaluate(`document.querySelector('#create-quiz-dialog').textContent.includes('Quiz mode')`), false);
  assert.equal(await browser.evaluate(`document.querySelector('[data-create-submit]').textContent.trim()`), 'Create quiz');
  assert.equal(await browser.evaluate(`document.querySelector('#create-quiz-dialog').textContent.includes('Create draft')`), false);
  await browser.evaluate(`(() => { const title=document.querySelector('[data-create-form] [name="title"]');title.value='Active by default';document.querySelector('[data-create-submit]').click(); })()`);
  await browser.until(() => browser.evaluate(`location.pathname === '/quizzes/${newPublicId}/edit' && document.querySelector('#quiz-builder')?.hasAttribute('v-cloak') === false`), 'new builder hydration');
  assert.equal(await browser.evaluate(`location.search`), '');
  assert.equal(await browser.evaluate(`document.querySelector('.input-switch[role="switch"]').checked`), true);
  assert.equal(await browser.evaluate(`document.querySelector('.label-switch').textContent.trim()`), 'Active');
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').textContent.trim()`), 'Save quiz');
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').classList.contains('btn-primary')`), true);
  assert.equal(newQuizPutCount, 0);
  assert.equal(newQuiz.status, 'draft');
  await browser.evaluate(`document.querySelector('[data-save-quiz]').click()`);
  await browser.until(() => newQuizPutCount === 1, 'empty active save rejection');
  await browser.until(() => browser.evaluate(`document.querySelector('[role="alert"]')?.textContent.includes('Add at least one question')`), 'publishable error');
  assert.equal(newQuiz.status, 'draft');
  await browser.evaluate(`document.querySelector('#builder-editor .empty-state .btn-primary').click()`);
  await browser.until(() => browser.evaluate(`document.querySelectorAll('.answer-list > .answer-row').length === 2`), 'default question choices');
  await browser.evaluate(`document.querySelector('[data-save-quiz]').click()`);
  await browser.until(() => newQuizPutCount === 2 && browser.evaluate(`document.querySelector('[data-save-quiz]').textContent.trim() === 'Saved'`), 'new quiz published');
  assert.equal(newQuiz.status, 'published');
  assert.equal(newQuiz.questions.length, 1);

  await browser.navigate(`${origin}/quizzes/${publicId}/edit`);
  await browser.until(() => browser.evaluate(`document.querySelector('#quiz-builder')?.hasAttribute('v-cloak') === false && document.body.textContent.includes('Which answer is correct?')`), 'builder hydration');
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').textContent.trim()`), 'Saved');
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').classList.contains('btn-default')`), true);
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').disabled`), false);
  assert.equal(quizPutCount, 0);
  assert.equal(await browser.evaluate(`document.querySelector('.answer-list').classList.contains('answer-list-grid')`), true);
  assert.equal(await browser.evaluate(`document.querySelectorAll('.answer-list > .answer-row').length`), 2);
  await browser.evaluate(`(() => { const layoutSelect=document.querySelector('.answer-editor select');layoutSelect.value='list';layoutSelect.dispatchEvent(new Event('change',{bubbles:true})); })()`);
  await browser.until(() => browser.evaluate(`!document.querySelector('.answer-list').classList.contains('answer-list-grid')`), 'builder list layout');
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').textContent.trim()`), 'Save quiz');
  assert.equal(quizPutCount, 0);
  await browser.evaluate(`(() => { const layoutSelect=document.querySelector('.answer-editor select');layoutSelect.value='grid';layoutSelect.dispatchEvent(new Event('change',{bubbles:true})); })()`);
  await browser.until(() => browser.evaluate(`document.querySelector('.answer-list').classList.contains('answer-list-grid')`), 'builder grid layout');
  await browser.until(() => browser.evaluate(`document.querySelector('[data-save-quiz]').textContent.trim()==='Saved'`), 'restored clean layout');
  assert.equal(quizPutCount, 0);
  await browser.evaluate(`document.querySelector('[data-save-quiz]').click()`);
  await browser.until(() => quizPutCount === 1, 'clean saved request');
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').textContent.trim()`), 'Saved');
  await browser.evaluate(`const field=document.querySelector('#builder-settings textarea');field.value='Changed description';field.dispatchEvent(new Event('input',{bubbles:true}))`);
  await browser.until(() => browser.evaluate(`document.querySelector('[data-save-quiz]').textContent.trim()==='Save quiz'`), 'dirty save action');
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').classList.contains('btn-primary')`), true);
  await browser.evaluate(`document.querySelector('[data-save-quiz]').click()`);
  await browser.until(() => browser.evaluate(`document.querySelector('[data-save-quiz]').textContent.trim()==='Saved'`), 'saved acknowledgement');
  assert.equal(quizPutCount, 2);
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').classList.contains('btn-default')`), true);
  assert.equal(await browser.evaluate(`document.querySelector('[data-save-quiz]').disabled`), false);
  assert.equal(await browser.evaluate(`[...document.querySelectorAll('button')].some(button => ['Publish','Unpublish'].includes(button.textContent.trim()))`), false);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Video URL')`), false);
  assert.equal(await browser.evaluate(`document.querySelector('#quiz-builder video, #quiz-builder iframe') === null`), true);
  assert.equal(await browser.evaluate(`document.querySelector('.input-switch[role="switch"]').checked`), true);
  assert.equal(await browser.evaluate(`document.querySelector('.label-switch').textContent.trim()`), 'Active');
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Single choice · grid')`), true);
  assert.equal(await browser.evaluate(`document.querySelector('#builder-editor textarea[placeholder="Type question text..."]') !== null`), true);
  assert.equal(await browser.evaluate(`[...document.querySelectorAll('#builder-editor button')].some(button => ['Preview','Edit'].includes(button.textContent.trim()))`), false);
  assert.equal(await browser.evaluate(`document.querySelector('.preview-paper, .preview-options') === null`), true);
  for (const removed of ['Practice','Passcode','Integrity','Short text','Multiple choice']) {
    assert.equal(await browser.evaluate(`document.body.textContent.includes(${JSON.stringify(removed)})`), false, removed);
  }
  await browser.evaluate(`document.querySelector('[aria-controls="builder-settings"]').click()`);
  assert.equal(await browser.evaluate(`document.querySelector('.builder-layout').dataset.activePanel`), 'settings');
  assert.equal(await browser.evaluate(`document.querySelector('#builder-settings').textContent.includes('Questions per attempt')`), true);
  assert.equal(await browser.evaluate(`document.querySelector('#builder-settings').textContent.includes('Total timer (minutes)')`), true);
  assert.equal(await browser.evaluate(`document.querySelector('#builder-settings input[type="number"][min="1"][max="120"][step="1"]').value`), '120');
  assert.equal(await browser.evaluate(`document.querySelector('#builder-settings input[placeholder="All questions"]').max`), '1');
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Fix validation errors')`), false);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Saved changes apply to students')`), false);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Show on my page')`), false);
  assert.equal(await browser.evaluate(`document.querySelector('[aria-label*="conflict" i]') === null`), true);

  await browser.navigate(`${origin}/results/quizzes/${publicId}`);
  await browser.until(() => browser.evaluate(`document.body.textContent.includes('Attempts (26)')`), 'attempt report');
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Paper revision')`), false);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Integrity timeline')`), false);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Completed or provisionally expired')`), false);
  assert.equal(await browser.evaluate(`document.body.textContent.includes('Export filtered CSV')`), false);
  assert.deepEqual(await browser.evaluate(`[...document.querySelectorAll('.attempt-filters [name]')].map(control => control.name)`),
    ['q','minScore','dateRange']);
  assert.deepEqual(await browser.evaluate(`[...document.querySelector('select[name="dateRange"]').options].map(option => option.textContent.trim())`),
    ['Last 24 hours','Yesterday','Last 7 days','Last 30 days','All time']);
  assert.deepEqual(await browser.evaluate(`Object.fromEntries(new URL(document.querySelector('.pagination a[href*="page=2"]').href).searchParams)`),
    {q:'Ali',minScore:'50',dateRange:'last_7_days',page:'2'});
  assert.deepEqual(browser.errors, []);
  console.log('Teacher assessment-only workspace browser behavior passed.');
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
