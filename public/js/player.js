import {grade, summarize} from './player-scoring.js?v=5';
import {createState, PlayerClock, editAnswer, submitAnswer, nextQuestion, finishTimed, quitQuiz,
  visibleDeadlines, hasPending, mergeServer, receiptOnly} from './player-state.js?v=7';
import {PlayerSync} from './player-sync.js?v=7';
import {PlayerTabGuard} from './player-tab-guard.js';
import {completionEmoji, resultCounts} from './player-presentation.js?v=1';
import {createPlayerView} from './player-view.js?v=7';

const config = JSON.parse(document.querySelector('#player-config').textContent);
const root = document.querySelector('#player-root');
const intro = document.querySelector('#quiz-introduction');
const statusBox = document.querySelector('#player-status');
const alertBox = document.querySelector('#player-alert');
const announcer = document.querySelector('#player-announcer');
const headerContext = document.querySelector('#player-header-context');
const headerTitle = document.querySelector('#player-header-title');
const headerSeparator = document.querySelector('#player-header-separator');
const headerProgress = document.querySelector('#player-header-progress');
const headerQuit = document.querySelector('#player-header-quit');
const storageKey = `edutest:player:${config.shareToken}`;
const admissionKey = `${storageKey}:admission`;
const tabGuard = new PlayerTabGuard(config.shareToken);
const t = (key, values = {}) => Object.entries(values).reduce(
  (text, [name, value]) => String(text).replaceAll(`{${name}}`, String(value)), config.ui[key] || key,
);
const view = createPlayerView(t);

let storageAvailable = true;
let state = read(storageKey);
let clock = null;
let timer = null;
let mediaTimer = null;
let running = false;
let starting = false;
let lastError = '';
let remoteConflict = null;

function read(key) {
  try { return JSON.parse(localStorage.getItem(key) || 'null'); }
  catch (_) { storageAvailable = false; return null; }
}
function write(key, value) {
  try {
    if (value === null) localStorage.removeItem(key);
    else localStorage.setItem(key, JSON.stringify(value));
  } catch (_) { storageAvailable = false; }
}
function persist() {
  if (state) write(storageKey, state);
  showAlert();
}
function notify(message) {
  announcer.textContent = '';
  requestAnimationFrame(() => { announcer.textContent = message; });
}
function showAlert() {
  const messages = [];
  if (tabGuard.unavailable) messages.push(t('tabGuardUnavailable'));
  if (!storageAvailable) messages.push(t('storageUnavailable'));
  if (lastError) messages.push(lastError);
  alertBox.textContent = messages.join(' ');
  alertBox.hidden = messages.length === 0;
}

async function request(path, {method = 'GET', body, credential, lock = method !== 'GET'} = {}) {
  if (lock && !tabGuard.allowed) throw Object.assign(new Error(t('tabBlocked')), {code: 'tab_inactive'});
  const headers = {'X-EduTest-Player': '1', Accept: 'application/json'};
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (credential) headers.Authorization = `Bearer ${credential}`;
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  try {
    const response = await fetch(config.apiBase + path, {
      method, headers, credentials: 'omit', cache: 'no-store', referrerPolicy: 'no-referrer',
      signal: controller.signal, body: body === undefined ? undefined : JSON.stringify(body),
    });
    let value;
    try { value = await response.json(); }
    catch (_) { throw new Error(t('networkError')); }
    if (!response.ok) throw Object.assign(new Error(value.error?.message || t('networkError')), {
      status: response.status, code: value.error?.code, fields: value.error?.fields,
    });
    lastError = '';
    return value;
  } catch (error) {
    if (!error.status || error.status >= 500) error.message = t('networkError');
    throw error;
  } finally {
    clearTimeout(timeout);
  }
}

const worker = new PlayerSync(() => state, request, {
  canSend: () => navigator.onLine && tabGuard.allowed,
  persist,
  conflict(server) {
    remoteConflict = server;
    lastError = t('conflictText');
    updateStatus();
  },
  change() {
    updateStatus();
    if (running && state) render();
  },
});

async function claim() {
  if (tabGuard.allowed) return true;
  if (await tabGuard.acquire()) return true;
  if (intro) intro.hidden = true;
  root.replaceChildren(view.blocked(state?.quiz?.title || '', activate));
  return false;
}

function updateHeader() {
  const visible = running && state;
  headerContext.hidden = !visible;
  headerTitle.textContent = visible ? state.quiz.title : '';
  const answering = visible && !state.finishReason;
  headerSeparator.hidden = !answering;
  headerProgress.hidden = !answering;
  headerQuit.hidden = !answering;
  if (answering) headerProgress.textContent = t('questionOf', {number: state.index + 1, total: state.items.length});
}

function updateStatus() {
  if (!state || !hasPending(state)) {
    statusBox.replaceChildren();
    return;
  }
  const failed = Boolean(worker.error || worker.paused);
  const label = t(failed ? 'resultSaveFailed' : navigator.onLine ? 'saving' : 'offline');
  statusBox.replaceChildren(view.status(label, failed ? () => {
    worker.paused = false;
    remoteConflict = null;
    worker.recover();
  } : null));
}

async function start(event) {
  event.preventDefault();
  const form = event.currentTarget;
  if (starting || !form.reportValidity() || !await claim()) return;
  starting = true;
  const button = form.querySelector('button[type="submit"]');
  const label = button.querySelector('[data-start-label]');
  button.disabled = true;
  label.textContent = t('starting');
  lastError = '';
  try {
    let admission = read(admissionKey);
    if (!admission) {
      admission = (await request(`/tickets/${config.shareToken}`, {lock: false})).data;
      write(admissionKey, admission);
    }
    const body = {ticket: admission.ticket};
    for (const [key, value] of new FormData(form)) body[key] = value;
    const response = await request('/starts', {method: 'POST', body});
    state = createState(response.data);
    write(admissionKey, null);
    persist();
    await activate(false);
  } catch (error) {
    if (['quiz_changed', 'credential_expired', 'invalid_credential'].includes(error.code)) write(admissionKey, null);
    lastError = error.message;
    for (const input of form.querySelectorAll('[aria-invalid]')) input.removeAttribute('aria-invalid');
    for (const field of Object.keys(error.fields || {})) form.elements.namedItem(field)?.setAttribute('aria-invalid', 'true');
    showAlert();
  } finally {
    starting = false;
    button.disabled = false;
    label.textContent = t('start');
  }
}

async function activate(recover = true) {
  if (!state || state.format !== 5 || state.shareToken !== config.shareToken) {
    window.location.replace(config.quizUrl);
    return;
  }
  if (!await claim()) return;
  running = true;
  if (intro) intro.hidden = true;
  clock = new PlayerClock(state);
  if (!state.finishReason && clock.now() >= Date.parse(state.expiresAt)) finishTimed(state);
  persist();
  clearInterval(timer);
  timer = setInterval(tick, 250);
  clearInterval(mediaTimer);
  mediaTimer = setInterval(() => { if (navigator.onLine) refreshMedia().catch(() => {}); }, 240000);
  render();
  tick();
  if (recover && navigator.onLine) worker.recover();
  else worker.flush();
}

function optionLetter(index) {
  let value = index + 1;
  let label = '';
  while (value > 0) {
    value--;
    label = String.fromCharCode(65 + value % 26) + label;
    value = Math.floor(value / 26);
  }
  return label;
}

function render() {
  if (!state) return;
  root.replaceChildren();
  const destination = state.finishReason ? 'results' : 'start';
  history.replaceState(null, '', `${config.quizUrl}/${destination}`);
  updateHeader();
  if (state.finishReason) renderResults();
  else renderQuestion();
  updateStatus();
  root.querySelector('h1,h2')?.focus({preventScroll: true});
}

function renderQuestion() {
  const question = state.quiz.questions[state.index];
  const item = state.items[state.index];
  root.append(view.question({
    title: state.quiz.title,
    identity: state.student?.name || '',
    question,
    item,
    options: question.options.map((option, index) => ({...option, letter: optionLetter(index)})),
    deadlines: visibleDeadlines(state),
    total: state.items.length,
    confirmedCount: state.answers.filter(answer => answer.answeredAt !== null).length,
    locked: state.phase === 'feedback',
    last: state.index === state.items.length - 1,
    hint: 'singleHint',
  }, {
    valid: () => state.draftCode !== null,
    onEdit: code => { const changed = editAnswer(state, code, clock.now()); if (changed) persist(); return changed; },
    onSubmit: () => confirmAnswer(false),
    onSkip: () => confirmAnswer(true),
    onNext: advance,
    media: renderMedia,
    feedback: () => state.quiz.settings.feedback === 'after_each'
      ? feedback(question, item, grade(question, {answerCodes: item.answerCodes})) : null,
  }));
}

function confirmAnswer(skipped) {
  if (!submitAnswer(state, skipped, clock.now())) return;
  persist();
  if (state.quiz.settings.feedback === 'at_end') nextQuestion(state, clock.now());
  worker.schedule(0);
  render();
}

function advance() {
  if (!nextQuestion(state, clock.now())) return;
  persist();
  worker.schedule(0);
  render();
}

function answerText(question, item) {
  const code = item.answerCodes[0];
  if (!code) return t('unanswered');
  const index = question.options.findIndex(option => option.code === code);
  const option = question.options[index];
  return `${optionLetter(index)}) ${option?.content || t('mediaImage')}`;
}

function feedback(question, item, award) {
  const correctAnswers = [];
  if (state.quiz.settings.showAnswers) {
    const index = question.options.findIndex(option => option.code === question.correctCode);
    const option = question.options[index];
    if (option) correctAnswers.push({
      text: `${optionLetter(index)}) ${option.content || t('mediaImage')}`,
      key: `o:${option.id}`,
      media: option.media,
    });
  }
  return view.feedback({
    verdict: award.result === 'skipped' ? 'unanswered' : award.result,
    answer: answerText(question, item),
    showAnswers: state.quiz.settings.showAnswers,
    correctLabel: 'correctAnswer',
    correctAnswers,
    explanation: state.quiz.settings.showAnswers && state.quiz.settings.showExplain ? question.explanation : '',
  }, renderMedia);
}

function renderResults() {
  const local = summarize(state.quiz.questions, state.answers);
  const official = state.result;
  const result = official?.score !== undefined ? official : local;
  const items = official?.items?.length ? official.items : local.items;
  const scoreVisible = Boolean(state.quiz.settings.showScore && result.percent !== undefined);
  root.append(view.results({
    title: state.quiz.title,
    identity: state.student?.name || '',
    heading: state.finishReason === 'exited' ? 'quitComplete' : 'complete',
    emoji: scoreVisible ? completionEmoji(result.percent) : '🎉',
    scoreVisible,
    percent: result.percent,
    total: Number(result.maxScore ?? state.quiz.questions.length),
    counts: scoreVisible ? resultCounts(items, state.quiz.questions.length) : null,
    status: state.result?.official ? 'confirmed' : 'provisional',
    scoreChanged: Boolean(official?.score !== undefined && Number(official.score) !== Number(local.score)),
    reason: state.finishReason === 'completed' ? null : state.finishReason,
    restart: state.result?.official && !hasPending(state) ? 'startAnother' : null,
    starting: false,
  }, startAnother));

  if (receiptOnly(state.quiz.settings)) return;
  root.append(view.review(state.quiz.questions.map((question, index) => ({
    question: question.content,
    verdict: (items[index]?.result === 'skipped' ? 'unanswered' : items[index]?.result) || 'unanswered',
  })), (details, index) => {
    const question = state.quiz.questions[index];
    const media = renderMedia(`q:${question.id}`, question.media);
    if (media) details.append(media);
    details.append(feedback(question, state.items[index], grade(question, state.answers[index] || {})));
  }));
}

function startAnother() {
  if (!state?.result?.official || hasPending(state)) return;
  write(storageKey, null);
  state = null;
  tabGuard.release();
  window.location.assign(config.quizUrl);
}

function tick() {
  if (!running || !state || state.finishReason || !clock) return;
  const now = clock.now();
  const remaining = Math.max(0, Math.ceil((Date.parse(state.expiresAt) - now) / 1000));
  for (const box of root.querySelectorAll('[data-deadline]')) {
    box.querySelector('[data-slot="remaining"]').textContent = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
    box.classList.toggle('is-urgent', remaining <= 30);
  }
  if (remaining > 0) return;
  finishTimed(state);
  persist();
  worker.schedule(0);
  render();
  notify(t('expired'));
}

function renderMedia(key, descriptor) {
  if (!descriptor) return null;
  const url = descriptor.embedUrl || descriptor.url;
  let kind = descriptor.type;
  if (kind === 'video') {
    try { kind = new URL(url).pathname.toLowerCase().endsWith('.mp4') ? 'video' : 'embed'; }
    catch (_) { kind = 'embed'; }
  }
  const wrapper = view.media(kind, key, url, element => view.mediaError(element, () => refreshMedia().then(render)));
  return wrapper;
}

async function refreshMedia() {
  if (!state || !navigator.onLine || !await claim()) return;
  const response = await request(`/assessments/${state.attemptId}/media`, {
    method: 'POST', body: {}, credential: state.credential,
  });
  if (!mergeServer(state, response.data)) throw new Error(t('conflictText'));
  persist();
}

function quit() {
  if (!state || state.finishReason || !window.confirm(t('quitConfirm'))) return;
  quitQuiz(state, clock.now());
  persist();
  worker.schedule(0);
  render();
}

headerQuit.addEventListener('click', quit);
window.addEventListener('online', () => { lastError = ''; showAlert(); worker.flush(); });
window.addEventListener('offline', () => { showAlert(); updateStatus(); });
window.addEventListener('beforeunload', event => {
  persist();
  if (state && hasPending(state)) { event.preventDefault(); event.returnValue = ''; }
});
window.addEventListener('pagehide', () => { persist(); tabGuard.release(); });

document.querySelector('#keep-local').addEventListener('click', () => {
  worker.paused = false;
  lastError = '';
  document.querySelector('#player-conflict').close();
  worker.flush();
});
document.querySelector('#use-server').addEventListener('click', () => {
  if (!remoteConflict || !state) return;
  const credential = state.credential;
  state = createState({...remoteConflict, credential});
  worker.paused = false;
  remoteConflict = null;
  persist();
  document.querySelector('#player-conflict').close();
  activate(false);
});

const admissionForm = document.querySelector('#quiz-admission');
if (admissionForm) {
  admissionForm.addEventListener('submit', start);
  admissionForm.querySelector('button[type="submit"]').disabled = false;
}

if (state?.format !== 5 || state?.shareToken !== config.shareToken) {
  if (state) write(storageKey, null);
  state = null;
}

if (config.page === 'intro') {
  if (state && !state.finishReason) window.location.replace(`${config.quizUrl}/start`);
} else if (!state) {
  window.location.replace(config.quizUrl);
} else if (config.page === 'start' && state.finishReason) {
  window.location.replace(`${config.quizUrl}/results`);
} else {
  activate();
}
showAlert();
