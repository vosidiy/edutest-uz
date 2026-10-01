import {grade, normalize, summarize} from './player-scoring.js?v=4';
import {createState, PlayerClock, editAnswer, submitAnswer, nextQuestion, finishTimed, deadlines, hasPending, uniqueKey, isFullscreenExit, recordActivity, receiptOnly} from './player-state.js?v=4';
import {PlayerSync} from './player-sync.js?v=4';
import {PlayerTabGuard} from './player-tab-guard.js';

const config = JSON.parse(document.querySelector('#player-config').textContent);
const ui = config.ui;
const root = document.querySelector('#player-root');
const status = document.querySelector('#player-status');
const alert = document.querySelector('#player-alert');
const intro = document.querySelector('#quiz-introduction');
const announcer = document.querySelector('#player-announcer');
const storageKey = `edutest:player:${config.shareToken}`;
const tabGuard = new PlayerTabGuard(config.shareToken);
let pageActive = true, pageGeneration = 0, runActive = false, resumeOnReturn = false;
let blockedRetry = null, startReservation = false;
let state = null, clock = null, storageAvailable = true, lastError = '', timer = null, mediaTimer = null, remoteConflict = null;
let connectionLost = !navigator.onLine;
let renderedPhase = '', warnedDeadline = '', starting = false, admission = null, mediaBusy = false;
let integrityReady = false, fullscreenWasActive = Boolean(document.fullscreenElement);
let blobBytes = 0;
const blobs = new Map();
const blobPending = new Set();
const t = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`{${name}}`, String(value)), ui[key] || key);
const node = (tag, className = '', text = null) => { const element = document.createElement(tag); element.className = className; if (text !== null) element.textContent = text; return element; };
const button = (label, action, variant = 'btn-primary') => { const item = node('button', `btn ${variant}`, label); item.type = 'button'; item.addEventListener('click', action); return item; };
const notify = text => { announcer.textContent = ''; requestAnimationFrame(() => { announcer.textContent = text; }); };
const optionLetter = index => {
  let value = index + 1;
  let label = '';
  while (value > 0) {
    value--;
    label = String.fromCharCode(65 + (value % 26)) + label;
    value = Math.floor(value / 26);
  }
  return label;
};

function fullscreenActive() { return Boolean(document.fullscreenElement); }
function updateFullscreenControls() {
  const active = fullscreenActive();
  document.querySelectorAll('[data-fullscreen-button]').forEach(control => { control.hidden = active; });
  document.querySelectorAll('[data-fullscreen-status]').forEach(control => { control.textContent = t(active ? 'fullscreenActive' : 'fullscreenRecommended'); });
}
async function enableFullscreen() {
  if (typeof document.documentElement.requestFullscreen !== 'function') {
    lastError = t('fullscreenUnavailable'); showAlert(); return;
  }
  try {
    await document.documentElement.requestFullscreen();
    fullscreenWasActive = fullscreenActive();
    lastError = ''; notify(t('fullscreenEnabled')); showAlert(); updateFullscreenControls();
  } catch (_) {
    lastError = t('fullscreenUnavailable'); showAlert();
  }
}
function fullscreenControl() {
  const wrapper = node('div', 'player-fullscreen-control');
  const control = button(t('enableFullscreen'), enableFullscreen, 'btn-default btn-sm');
  control.dataset.fullscreenButton = '';
  const message = node('span', '', t(fullscreenActive() ? 'fullscreenActive' : 'fullscreenRecommended'));
  message.dataset.fullscreenStatus = ''; message.setAttribute('role', 'status');
  wrapper.append(control, message);
  return wrapper;
}
function recordIntegrity(type) {
  if (!canRun() || !integrityReady || !state || state.mode !== 'assessment' || !state.quiz.settings.cheatCheck || state.finishReason) return;
  if (state.events.length >= 1000) return;
  state.events.push({key: uniqueKey(), type, happenedAt: clock.iso(), durationMs: null});
  changed();
}
function handleFullscreenChange() {
  const active = fullscreenActive();
  if (isFullscreenExit(fullscreenWasActive, active)) recordIntegrity('fullscreen_exit');
  fullscreenWasActive = active;
  updateFullscreenControls();
}

function readStorage(key) {
  try { return JSON.parse(localStorage.getItem(key) || 'null'); }
  catch (_) { storageAvailable = false; return null; }
}
function store(key, value) {
  try { if (value === null) localStorage.removeItem(key); else localStorage.setItem(key, JSON.stringify(value)); }
  catch (_) { storageAvailable = false; showAlert(); }
}
function persist() { if (state) { clock?.now(); store(storageKey, state); } }
function showAlert() {
  const messages = [];
  if (tabGuard.unavailable) messages.push(t('tabGuardUnavailable'));
  if (!storageAvailable) messages.push(t('storageUnavailable'));
  if ((connectionLost || !navigator.onLine) && state) messages.push(t('offline'));
  alert.classList.toggle('alert-error', Boolean(state && (connectionLost || !navigator.onLine)));
  alert.classList.toggle('alert-warning', !state || (!connectionLost && navigator.onLine));
  if (lastError) messages.push(lastError);
  alert.textContent = messages.join(' ');
  alert.hidden = !messages.length;
}

async function request(path, {method = 'GET', body, credential} = {}) {
  if (method !== 'GET' && (!pageActive || !tabGuard.allowed)) throw Object.assign(new Error(t('tabBlocked')), {code:'tab_inactive'});
  const headers = {'X-EduTest-Player': '1', Accept: 'application/json'};
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (credential) headers.Authorization = `Bearer ${credential}`;
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  try {
    const response = await fetch(config.apiBase + path, {method, headers, credentials: 'omit', cache: 'no-store', referrerPolicy: 'no-referrer',
      signal: controller.signal, body: body === undefined ? undefined : JSON.stringify(body)});
    let value;
    try { value = await response.json(); } catch (_) { throw new Error(t('networkError')); }
    if (!response.ok) throw Object.assign(new Error(value.error?.message || t('networkError')), {status: response.status, code: value.error?.code, fields: value.error?.fields});
    connectionLost = false;
    if (lastError === t('networkError')) lastError = '';
    showAlert();
    return value;
  } catch (error) {
    if (!error.status || error.status >= 500) { connectionLost = true; error.message = t('networkError'); }
    throw error;
  } finally { clearTimeout(timeout); }
}

const worker = new PlayerSync(() => state, request, {
  canSend: () => canRun(),
  persist,
  error(error) { lastError = error.message; showAlert(); },
  conflict(server) { remoteConflict = server; document.querySelector('#player-conflict').showModal(); },
  change() {
    updateStatus();
    if (runActive && !blockedRetry && state && (renderedPhase !== viewSignature())) render();
  }
});

function canRun() { return pageActive && runActive && tabGuard.allowed && !blockedRetry; }
function needsOwnership() { return startReservation || state?.practiceRetry?.needsRecovery || (state && (!state.finishReason || hasPending(state))); }
function releaseCompleted() {
  if (state?.finishReason && !needsOwnership() && !worker.busy && !mediaBusy && !starting) tabGuard.release();
}
function showBlocked() {
  runActive = false;
  clearInterval(timer); clearInterval(mediaTimer); clearTimeout(worker.timer);
  document.querySelector('#player-conflict').close();
  const panel = node('section', 'card player-error');
  panel.dataset.tabBlocked = '';
  panel.setAttribute('role', 'alert');
  panel.append(node('p', '', t('tabBlocked')), button(t('retry'), () => blockedRetry?.(), 'btn-primary'));
  root.replaceChildren(panel);
  status.replaceChildren();
  root.focus({preventScroll:true});
  root.scrollIntoView({block:'start', behavior:'instant'});
}
async function claimTab(retry) {
  if (!pageActive) return false;
  const generation = pageGeneration;
  const acquired = await tabGuard.acquire();
  if (!pageActive || generation !== pageGeneration) return false;
  if (!acquired) { blockedRetry = retry; showBlocked(); return false; }
  blockedRetry = null;
  root.querySelector('[data-tab-blocked]')?.remove();
  showAlert();
  return true;
}
function updateStatus() {
  releaseCompleted();
  if (!state || blockedRetry || !runActive) return;
  status.replaceChildren();
  let label = state.mode === 'practice' ? t('practiceNotice') : worker.busy ? t(connectionLost ? 'reconnecting' : 'saving') : hasPending(state) ? t('pending') : t('saved');
  status.append(node('span', '', label));
  if (state.mode === 'assessment' && hasPending(state) && !worker.busy) status.append(button(t('retry'), () => { lastError = ''; worker.paused = false; showAlert(); worker.flush(); }, 'btn-default btn-sm'));
  showAlert();
}

function viewSignature() { return `${state.index}:${state.phase}:${Boolean(state.finishReason)}:${Boolean(state.result)}:${state.finishReason && hasPending(state)}`; }
async function activate(refresh = true, explicitResume = false) {
  if (!state || state.format !== 4 || state.quiz.shareToken !== config.shareToken) return missing();
  if (!pageActive) return;
  if (needsOwnership() && !await claimTab(() => activate(refresh, explicitResume))) return;
  blockedRetry = null;
  runActive = true;
  if (intro) intro.hidden = true;
  clock = new PlayerClock(state);
  lastError = '';
  render();
  clearInterval(timer); timer = setInterval(tick, 250);
  clearInterval(mediaTimer); mediaTimer = setInterval(() => { if (navigator.onLine) refreshMedia().catch(() => {}); }, 240000);
  tick();
  if (explicitResume && !state.finishReason) { recordActivity(state, clock.now()); persist(); }
  setupIntegrity();
  if (refresh && state.mode === 'assessment') worker.recover();
  else worker.flush();
}
function showLegacy() {
  worker.stop();
  if (intro) intro.hidden = true;
  const box = node('section', 'card player-error');
  box.append(node('p', '', t('legacyProgress')));
  // Keep the original storage value available for support/review; never invent occurrence times.
  if (state?.quiz?.questions && state?.items) {
    const pre = node('pre', 'player-local-backup', JSON.stringify({title:state.quiz.title, questions:state.quiz.questions.map((q, i) => ({
      question:q.content, selected:state.items[i]?.answerCodes || [], text:state.items[i]?.textAnswer || ''
    }))}, null, 2));
    const disclosure = node('details'); disclosure.append(node('summary', '', t('localProgress')), pre); box.append(disclosure);
  }
  root.replaceChildren(box);
}
function missing() {
  root.replaceChildren(node('p', 'card player-error', t('missingState')));
  const link = node('a', 'btn btn-primary', t('introduction')); link.href = config.quizUrl; root.append(link);
}

async function start(event) {
  event.preventDefault();
  if (state && state.format !== 4) { showLegacy(); return; }
  if (starting) return;
  if (state?.format === 4 && (!state.finishReason || hasPending(state))) { await activate(); return; }
  const form = event.currentTarget;
  if (!await claimTab(() => start({preventDefault(){},currentTarget:form}))) return;
  if (starting) return;
  starting = true;
  startReservation = true;
  let definitiveFailure = false;
  const startButton = form.querySelector('button[type="submit"]');
  startButton.disabled = true; startButton.textContent = t('starting'); lastError = ''; showAlert();
  form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
  try {
    admission ||= readStorage(storageKey + ':admission');
    if (admission?.attemptId) {
      try {
        const recovered = await request(`/assessments/${admission.attemptId}`, {credential: admission.credential});
        recovered.data.credential = admission.credential;
        state = createState(recovered.data, recovered.meta.timestamp);
        startReservation = false;
        persist(); await activate(false); return;
      } catch (error) { if (error.status !== 401) throw error; }
    }
    if (!form.reportValidity()) { definitiveFailure = true; return; }
    if (!admission) { admission = (await request(`/tickets/${config.shareToken}`)).data; store(storageKey + ':admission', admission); }
    const body = {ticket: admission.ticket};
    if (config.mode === 'assessment') for (const [key, value] of new FormData(form)) body[key] = value;
    const response = await request('/starts', {method: 'POST', body});
    state = createState(response.data, response.meta.timestamp);
    store(storageKey + ':admission', null); admission = null;
    startReservation = false;
    persist(); await activate(false);
  } catch (error) {
    definitiveFailure = Boolean(error.status && error.status < 500);
    if (['quiz_changed', 'credential_expired'].includes(error.code)) { admission = null; store(storageKey + ':admission', null); }
    lastError = error.message;
    for (const key of Object.keys(error.fields || {})) form.elements.namedItem(key)?.setAttribute('aria-invalid', 'true');
    form.querySelector('[aria-invalid="true"]')?.focus();
    showAlert();
  } finally {
    starting = false; startButton.disabled = false; startButton.textContent = t('start');
    if (definitiveFailure) { startReservation = false; tabGuard.release(); }
    releaseCompleted();
  }
}

async function retryPractice() {
  if (starting || state?.mode !== 'practice' || !state.finishReason) return;
  if (!await claimTab(async () => { await activate(); await retryPractice(); })) return;
  if (starting) return;
  starting = true;
  startReservation = true;
  let definitiveFailure = false;
  const retryButton = root.querySelector('[data-try-again]');
  retryButton.disabled = true; retryButton.textContent = t('starting');
  lastError = ''; showAlert();
  const retryKey = storageKey + ':retry';
  try {
    // Bind pending admission to the completed run so refresh/lost acknowledgements reuse it.
    let pending = state.practiceRetry || readStorage(retryKey);
    if (!pending || pending.fromStartedAt !== state.startedAt) {
      const ticket = (await request(`/tickets/${config.shareToken}`)).data;
      if (ticket.mode !== 'practice') {
        store(storageKey, null); store(retryKey, null); store(storageKey + ':admission', null);
        window.location.assign(config.quizUrl);
        return;
      }
      pending = {fromStartedAt: state.startedAt, ticket: ticket.ticket};
      state.practiceRetry = pending; store(retryKey, pending); persist();
    }
    pending.needsRecovery = true;
    state.practiceRetry = pending; store(retryKey, pending); persist();
    const response = await request('/starts', {method: 'POST', body: {ticket: pending.ticket}});
    const fresh = createState(response.data, response.meta.timestamp);
    state = fresh;
    startReservation = false;
    warnedDeadline = ''; renderedPhase = ''; remoteConflict = null;
    announcer.textContent = '';
    persist(); store(retryKey, null);
    await activate(false);
  } catch (error) {
    definitiveFailure = Boolean(error.status && error.status < 500);
    if (definitiveFailure && state.practiceRetry) {
      state.practiceRetry.needsRecovery = false; store(retryKey, state.practiceRetry); persist();
    }
    if (['quiz_changed', 'credential_expired', 'invalid_credential'].includes(error.code)) {
      delete state.practiceRetry; store(retryKey, null); persist();
    }
    lastError = error.message; showAlert();
  } finally {
    starting = false;
    if (definitiveFailure) { startReservation = false; tabGuard.release(); }
    retryButton.disabled = false; retryButton.textContent = t('tryAgain');
    // A recovered start may already have timed out and rendered a new results button.
    const visibleRetry = root.querySelector('[data-try-again]');
    if (visibleRetry) { visibleRetry.disabled = false; visibleRetry.textContent = t('tryAgain'); }
    releaseCompleted();
  }
}

function changed(immediate = false) {
  if (!canRun()) return;
  persist(); updateStatus(); if (state.mode === 'assessment') worker.schedule(immediate ? 0 : 400);
  if (renderedPhase !== viewSignature()) render();
}
function submit(reason = 'answered') {
  if (!canRun()) return;
  if (!submitAnswer(state, reason, clock.now())) { changed(true); render(); return; }
  persist(); // Confirmation and its outbox entry are durable before navigation.
  const result = grade(state.quiz.questions[state.index], state.items[state.index]);
  if (state.quiz.settings.feedback === 'at_end') nextQuestion(state);
  else if (!receiptOnly(state.quiz.settings)) notify(t(result.result));
  changed(true); render(); tick();
}
function next() { if (canRun() && (nextQuestion(state, clock.now()) || state.finishReason)) { changed(true); render(); tick(); } }

function startAnotherAssessment() {
  if (!state || state.mode !== 'assessment' || !state.result?.confirmed || hasPending(state)) return;
  store(storageKey, null); store(storageKey + ':admission', null); state = null; tabGuard.release();
  window.location.assign(config.quizUrl);
}

function render() {
  if (!state) return;
  renderedPhase = viewSignature();
  root.replaceChildren();
  history.replaceState(null, '', `${config.quizUrl}/${state.finishReason ? 'results' : 'play'}`);
  if (state.finishReason) renderResults(); else renderQuestion();
  updateStatus();
  root.querySelector('h1, h2')?.focus({preventScroll: true});
}

function renderQuestion() {
  const question = state.quiz.questions[state.index];
  const item = state.items[state.index];
  const top = node('div', 'player-topbar');
  const timers = node('div', 'player-timers');
  top.append(node('p', 'player-quiz-title', state.quiz.title), timers);
  root.append(top);
  for (const deadline of deadlines(state)) {
    const box = node('div', 'player-timer'); box.dataset.deadline = String(deadline.at); box.setAttribute('aria-live', 'off');
    box.append(node('span', '', t(deadline.label)), node('strong', '', ''));
    timers.append(box);
  }
  if (state.mode === 'assessment' && state.quiz.settings.cheatCheck) timers.append(fullscreenControl());
  const progress = node('progress', 'player-progress'); progress.max = state.items.length; progress.value = state.items.filter(row => row.status === 'locked').length; progress.setAttribute('aria-label', t('progress')); root.append(progress);
  const paper = node('section', 'card player-question');
  paper.append(node('span', 'player-question-number', t('questionOf', {number: state.index + 1, total: state.items.length})));
  const heading = node('h2', '', question.content); heading.tabIndex = -1; heading.id = 'current-question'; paper.append(heading); paper.setAttribute('aria-labelledby', heading.id);
  appendMedia(paper, `q:${question.id}`, question.media);
  const form = node('form');
  const answers = node('fieldset', 'player-answers'); answers.disabled = item.status === 'locked';
  answers.append(node('legend', '', t(question.type === 'short_text' ? 'textHint' : question.type === 'single_choice' ? 'singleHint' : 'multiHint')));
  const submitButton = button(t('submit'), () => {}, 'btn-primary btn-lg'); submitButton.type = 'submit';
  const valid = () => question.type === 'short_text' ? normalize(item.textAnswer) !== '' : item.answerCodes.length > 0;
  submitButton.disabled = !valid();
  if (question.type === 'short_text') {
    const input = node('input', 'form-control'); input.type = 'text'; input.maxLength = 500; input.value = item.textAnswer; input.autocomplete = 'off'; input.setAttribute('aria-label', t('textHint'));
    input.addEventListener('input', () => { if (!canRun()) return; editAnswer(state, {textAnswer: input.value}, clock.now()); submitButton.disabled = !valid(); changed(); });
    answers.append(input);
  } else {
    for (const [optionIndex, option] of question.options.entries()) {
      const letter = optionLetter(optionIndex);
      const label = node('label', `player-option${item.answerCodes.includes(option.code) ? ' is-selected' : ''}`);
      const input = node('input'); input.type = question.type === 'single_choice' ? 'radio' : 'checkbox'; input.name = 'answer'; input.value = option.code; input.checked = item.answerCodes.includes(option.code);
      const copy = node('span', 'player-option-content');
      copy.append(node('span', 'player-option-letter', `${letter})`), node('span', 'player-option-copy', option.content));
      appendMedia(copy, `o:${option.id}`, option.media);
      // Media-only choices still need an accessible option label.
      input.setAttribute('aria-label', `${letter}) ${option.content || t('mediaImage')}`);
      input.addEventListener('change', () => {
        if (!canRun()) return;
        const selected = [...answers.querySelectorAll('input:checked')].map(element => element.value);
        editAnswer(state, {answerCodes: selected}, clock.now());
        answers.querySelectorAll('.player-option').forEach(row => row.classList.toggle('is-selected', row.querySelector('input').checked));
        submitButton.disabled = !valid(); changed();
      });
      label.append(input, copy); answers.append(label);
    }
  }
  form.append(answers);
  form.addEventListener('submit', event => { event.preventDefault(); if (valid()) submit(); });
  if (item.status !== 'locked') {
    const actions = node('div', 'player-actions'); actions.append(button(t('skip'), () => submit('skipped'), 'btn-default'), submitButton); form.append(actions);
  }
  paper.append(form);
  if (item.status === 'locked') {
    if (state.quiz.settings.feedback === 'after_each' && !receiptOnly(state.quiz.settings)) paper.append(feedback(question, item, grade(question, item)));
    const actions = node('div', 'player-actions'); actions.append(button(t(state.index === state.items.length - 1 ? 'finish' : 'next'), next, 'btn-primary btn-lg')); paper.append(actions);
  }
  root.append(paper);
  const help = node('p', 'player-help', t('questionHint')); root.append(help);
}

function answerText(question, item) {
  if (item.answerStatus === 'not_reached') return t('unanswered');
  return question.type === 'short_text'
    ? item.textAnswer || t('unanswered')
    : question.options
      .map((option, index) => ({option, index}))
      .filter(({option}) => item.answerCodes.includes(option.code))
      .map(({option, index}) => `${optionLetter(index)}) ${option.content || t('mediaImage')}`)
      .join('; ') || t('unanswered');
}
function feedback(question, item, result) {
  const settings = state.quiz.settings;
  const box = node('section', `player-feedback ${result.result}`);
  box.append(node('h3', '', t(result.result)), node('p', '', `${t('yourAnswer')}: ${answerText(question, item)}`));
  if (settings.showAnswers) {
    box.append(node('strong', '', t(question.type === 'short_text' ? 'acceptedAnswers' : 'correctAnswer')));
    if (question.type === 'short_text') box.append(node('p', '', question.acceptedAnswers.join(' / ')));
    else for (const [index, option] of question.options.entries()) {
      if (!question.correctCodes.includes(option.code)) continue;
      const answer = node('p', '', `${optionLetter(index)}) ${option.content || t('mediaImage')}`); box.append(answer); appendMedia(box, `o:${option.id}`, option.media);
    }
    if (settings.showExplain && question.explanation) box.append(node('strong', '', t('explanation')), node('p', '', question.explanation));
  }
  return box;
}

function renderResults() {
  const local = summarize(state.quiz.questions, state.items);
  const result = state.result || local;
  const card = node('section', 'card player-results-header');
  const icon = node('span', 'player-completion-icon', '✓'); icon.setAttribute('aria-hidden', 'true'); card.append(icon);
  const heading = node('h1', '', t('complete')); heading.tabIndex = -1; card.append(heading, node('p', '', state.quiz.title));
  if (state.quiz.settings.showScore) card.append(node('div', 'player-score', `${result.percent}%`), node('p', '', `${result.score} / ${result.maxScore} ${t('questionsScore')}`));
  else card.append(node('p', '', t('hiddenScore')));
  card.append(node('p', 'player-help', t(state.mode === 'practice' ? 'practiceResult' : state.result ? 'confirmed' : 'provisional')));
  if (state.result && state.quiz.settings.showScore && state.result.score !== local.score) card.append(node('p', 'alert alert-warning', t('scoreChanged')));
  if (state.finishReason !== 'completed') card.append(node('p', '', t(state.finishReason)));
  if (state.mode === 'practice') {
    const actions = node('div', 'player-actions');
    const retryButton = button(t('tryAgain'), retryPractice, 'btn-primary btn-lg');
    retryButton.dataset.tryAgain = ''; retryButton.disabled = starting;
    actions.append(retryButton); card.append(actions);
  } else if (state.result?.confirmed && !hasPending(state)) {
    const actions = node('div', 'player-actions');
    actions.append(button(t('startAnother'), startAnotherAssessment, 'btn-primary btn-lg')); card.append(actions);
  }
  root.append(card);
  if (receiptOnly(state.quiz.settings)) return;
  const review = node('section', 'player-review'); review.append(node('h2', '', t('review')));
  state.quiz.questions.forEach((question, index) => {
    const item = state.items[index];
    const award = result.items.find(row => row.questionId === question.id) || grade(question, item.answerStatus === 'answered' ? item : {});
    const details = node('details', 'card player-review-item'); const summary = node('summary');
    summary.append(node('span', 'player-question-number', String(index + 1)), node('strong', '', question.content), node('span', 'badge', t(award.result)));
    details.append(summary);
    // Load review media only when opened, keeping long quizzes lightweight.
    details.addEventListener('toggle', () => {
      if (!details.open || details.dataset.loaded) return;
      details.dataset.loaded = 'true'; appendMedia(details, `q:${question.id}`, question.media); details.append(feedback(question, item, award));
    });
    review.append(details);
  });
  root.append(review);
}

function tick() {
  if (!canRun() || !state || state.finishReason || !clock) return;
  const now = clock.now();
  for (const box of root.querySelectorAll('[data-deadline]')) {
    const remaining = Math.max(0, Math.ceil((Date.parse(state.expiresAt) - now) / 1000));
    box.querySelector('strong').textContent = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
    box.classList.toggle('is-urgent', remaining <= 30);
  }
  const due = deadlines(state)[0];
  if (!due) return;
  if (due.at - now <= 30000 && warnedDeadline !== `${due.at}`) { warnedDeadline = `${due.at}`; notify(t('timeWarning')); }
  if (now < due.at) return;
  finishTimed(state, due.reason); changed(true); render(); notify(t(due.reason));
}

function getMedia(key) {
  const [kind, id] = key.split(':');
  if (kind === 'q') return state.quiz.questions.find(question => question.id === id)?.media;
  return state.quiz.questions.flatMap(question => question.options).find(option => option.id === id)?.media;
}
function directVideo(url) {
  try { return new URL(url).pathname.toLowerCase().endsWith('.mp4'); }
  catch (_) { return false; }
}
function appendMedia(parent, key, descriptor) {
  if (!descriptor) return;
  const wrapper = node('div', 'player-media'); wrapper.dataset.mediaKey = key;
  let media;
  if (descriptor.type === 'image') { media = node('img'); media.alt = t('mediaImage'); }
  else if (descriptor.type === 'audio') { media = node('audio'); media.controls = true; media.preload = 'metadata'; }
  else if (directVideo(descriptor.embedUrl || descriptor.url)) { media = node('video'); media.controls = true; media.preload = 'metadata'; }
  else { media = node('iframe'); media.title = t('mediaVideo'); media.loading = 'lazy'; media.referrerPolicy = 'strict-origin-when-cross-origin'; media.allow = 'fullscreen; picture-in-picture'; media.setAttribute('allowfullscreen', ''); }
  media.src = blobs.get(key)?.url || descriptor.embedUrl || descriptor.url;
  media.addEventListener('error', () => {
    if (wrapper.querySelector('button')) return;
    wrapper.append(node('p', 'player-help', t('mediaError')), button(t('retry'), async () => {
      try { await refreshMedia(); const holder = node('div'); appendMedia(holder, key, getMedia(key)); wrapper.replaceWith(...holder.childNodes); }
      catch (error) { lastError = error.message; showAlert(); }
    }, 'btn-default btn-sm'));
  });
  wrapper.append(media); parent.append(wrapper);
  // Bounded in-memory image cache for revisiting feedback while disconnected; never written to server/tab storage.
  if (descriptor.type === 'image' && !blobs.has(key) && !blobPending.has(key) && blobBytes < 32 * 1024 * 1024) {
    blobPending.add(key);
    fetch(descriptor.url, {credentials: 'omit', referrerPolicy: 'no-referrer'}).then(response => { if (!response.ok) throw new Error(); return response.blob(); })
      .then(blob => { if (blobBytes + blob.size <= 32 * 1024 * 1024) { blobBytes += blob.size; blobs.set(key, {url: URL.createObjectURL(blob)}); } })
      .catch(() => {}).finally(() => blobPending.delete(key));
  }
}
async function refreshMedia() {
  if (!state || mediaBusy) return;
  if (!await claimTab(async () => { await activate(); if (runActive) await refreshMedia(); })) return;
  if (mediaBusy) return;
  const originalState = state;
  mediaBusy = true;
  try {
    const path = state.mode === 'practice' ? '/practice/media' : `/assessments/${state.attemptId}/media`;
    const response = await request(path, {method: 'POST', body: {}, credential: state.credential});
    if (state !== originalState) return;
    if (response.data.credential) state.credential = response.data.credential;
    const incoming = new Map(response.data.quiz.questions.map(question => [question.id, question]));
    for (const question of state.quiz.questions) {
      question.media = incoming.get(question.id)?.media || null;
      const options = new Map((incoming.get(question.id)?.options || []).map(option => [option.id, option]));
      for (const option of question.options) option.media = options.get(option.id)?.media || null;
    }
    persist();
  } finally { mediaBusy = false; releaseCompleted(); }
}

function setupIntegrity() {
  if (integrityReady || state.mode !== 'assessment' || !state.quiz.settings.cheatCheck) return;
  integrityReady = true;
  fullscreenWasActive = fullscreenActive();
  document.addEventListener('visibilitychange', () => { if (document.hidden) recordIntegrity('tab_hidden'); tick(); });
  updateFullscreenControls();
}

document.querySelector('#keep-local').addEventListener('click', () => document.querySelector('#player-conflict').close());
document.querySelector('#use-server').addEventListener('click', () => {
  if (!remoteConflict) return;
  const credential = state.credential;
  state = createState({...remoteConflict, credential}, new Date(clock.now()).toISOString());
  worker.paused = false; remoteConflict = null; document.querySelector('#player-conflict').close(); persist(); activate();
});
window.addEventListener('online', () => { updateStatus(); worker.flush(); });
window.addEventListener('offline', () => { connectionLost = true; updateStatus(); });
window.addEventListener('beforeunload', event => { persist(); if (state && hasPending(state)) { event.preventDefault(); event.returnValue = ''; } });
window.addEventListener('pagehide', () => {
  persist();
  resumeOnReturn = runActive || startReservation || Boolean(blockedRetry);
  pageActive = false; pageGeneration++; runActive = false;
  clearInterval(timer); clearInterval(mediaTimer); clearTimeout(worker.timer);
  tabGuard.release();
});
window.addEventListener('pageshow', event => {
  if (!event.persisted) return;
  pageActive = true;
  if (resumeOnReturn && state) activate();
  else if (resumeOnReturn && admissionForm) start({preventDefault(){},currentTarget:admissionForm});
});
document.querySelectorAll('.player-schedule time').forEach(element => { element.textContent = new Intl.DateTimeFormat(undefined, {dateStyle: 'medium', timeStyle: 'short'}).format(new Date(element.dateTime)); });
document.querySelectorAll('[data-fullscreen-button]').forEach(control => control.addEventListener('click', enableFullscreen));
document.addEventListener('fullscreenchange', handleFullscreenChange);
updateFullscreenControls();
const admissionForm = document.querySelector('#quiz-admission');
if (admissionForm) {
  admissionForm.addEventListener('submit', start);
  admissionForm.querySelector('button[type="submit"]').disabled = false;
}

state = readStorage(storageKey);
if (state && state.format !== 4) { showLegacy(); }
else if (config.page === 'intro') {
  const resume = document.querySelector('#resume-quiz');
  if (state?.format === 4) { resume.hidden = false; resume.addEventListener('click', () => activate(true, true)); }
} else if (state?.format === 4) {
  activate();
} else missing();
showAlert();
