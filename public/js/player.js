import {grade, normalize, summarize} from './player-scoring.js?v=4';
import {createState, PlayerClock, editAnswer, submitAnswer, nextQuestion, finishTimed, quitQuiz, deadlines, visibleDeadlines, hasPending, uniqueKey, isFullscreenExit, recordActivity, receiptOnly} from './player-state.js?v=5';
import {PlayerSync} from './player-sync.js?v=6';
import {PlayerTabGuard} from './player-tab-guard.js';
import {completionEmoji, resultCounts} from './player-presentation.js?v=1';
import {createPlayerView} from './player-view.js?v=5';

const config = JSON.parse(document.querySelector('#player-config').textContent);
const ui = config.ui;
const root = document.querySelector('#player-root');
const status = document.querySelector('#player-status');
const alert = document.querySelector('#player-alert');
const intro = document.querySelector('#quiz-introduction');
const announcer = document.querySelector('#player-announcer');
const headerContext = document.querySelector('#player-header-context');
const headerMode = document.querySelector('#player-header-mode');
const headerSeparator = document.querySelector('#player-header-separator');
const headerProgress = document.querySelector('#player-header-progress');
const headerQuit = document.querySelector('#player-header-quit');
const storageKey = `edutest:player:${config.shareToken}`;
const tabGuard = new PlayerTabGuard(config.shareToken);
let pageActive = true, pageGeneration = 0, runActive = false, resumeOnReturn = false;
let blockedRetry = null, retryingTab = false, startReservation = false;
let state = null, clock = null, storageAvailable = true, lastError = '', timer = null, mediaTimer = null, remoteConflict = null;
let connectionLost = !navigator.onLine;
let renderedPhase = '', warnedDeadline = '', starting = false, admission = null, mediaBusy = false;
let statusSignature = '';
let restoring = null, runGeneration = 0, syncAtIntro = false;
let integrityReady = false, fullscreenWasActive = Boolean(document.fullscreenElement);
let blobBytes = 0;
const blobs = new Map();
const blobPending = new Set();
const t = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`{${name}}`, String(value)), ui[key] || key);
const view = createPlayerView(t);
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
function persist() { if (state && pageActive && tabGuard.allowed) { clock?.now(); store(storageKey, state); } }
function showAlert() {
  if (blockedRetry) { alert.textContent = ''; alert.hidden = true; return; }
  const messages = [];
  if (tabGuard.unavailable) messages.push(t('tabGuardUnavailable'));
  if (!storageAvailable) messages.push(t('storageUnavailable'));
  alert.classList.remove('alert-error');
  alert.classList.add('alert-warning');
  if (lastError) messages.push(lastError);
  alert.textContent = messages.join(' ');
  alert.hidden = !messages.length;
}

async function request(path, {method = 'GET', body, credential} = {}) {
  const generation = runGeneration;
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
    if (generation === runGeneration) {
      connectionLost = false;
      if (lastError === t('networkError')) lastError = '';
      showAlert();
    }
    return value;
  } catch (error) {
    if (!error.status || error.status >= 500) { if (generation === runGeneration) connectionLost = true; error.message = t('networkError'); }
    throw error;
  } finally { clearTimeout(timeout); }
}

const worker = new PlayerSync(() => state, request, {
  canSend: () => pageActive && (runActive || syncAtIntro) && tabGuard.allowed && !blockedRetry,
  persist,
  conflict(server) { remoteConflict = server; updateStatus(); },
  change() {
    updateStatus();
    if (runActive && !blockedRetry && state && (renderedPhase !== viewSignature())) render();
    if (syncAtIntro && !worker.busy && !worker.error && !worker.paused) {
      queueMicrotask(() => { if (syncAtIntro && !starting && !restoring) resumeIfActive(); });
    }
  }
});

function canRun() { return pageActive && runActive && tabGuard.allowed && !blockedRetry; }
function updateHeader() {
  const contextual = Boolean(state && runActive && !blockedRetry);
  headerContext.hidden = !contextual;
  if (!contextual) {
    headerSeparator.hidden = true;
    headerProgress.hidden = true;
    headerQuit.hidden = true;
    return;
  }
  headerMode.textContent = t(state.mode);
  const answering = !state.finishReason;
  headerSeparator.hidden = !answering;
  headerProgress.hidden = !answering;
  headerProgress.textContent = answering ? t('questionOf', {number: state.index + 1, total: state.items.length}) : '';
  headerQuit.hidden = !answering;
  headerQuit.disabled = answering && !canRun();
}
function needsOwnership() { return startReservation || state?.practiceRetry?.needsRecovery || (state && (!state.finishReason || hasPending(state))); }
function releaseCompleted() {
  if (state?.finishReason && !needsOwnership() && !worker.busy && !mediaBusy && !starting) tabGuard.release();
}
function showBlocked() {
  runActive = false;
  if (intro) intro.hidden = true;
  clearInterval(timer); clearInterval(mediaTimer); clearTimeout(worker.timer);
  document.querySelector('#player-conflict').close();
  const title = state?.quiz?.title ?? intro?.querySelector('#quiz-title')?.textContent ?? '';
  root.replaceChildren(view.blocked(title, retryBlocked));
  status.replaceChildren(); statusSignature = '';
  showAlert();
  updateHeader();
  root.focus({preventScroll:true});
  root.scrollIntoView({block:'start', behavior:'instant'});
}
async function retryBlocked() {
  if (retryingTab || !blockedRetry) return;
  retryingTab = true;
  try {
    if (!await claimTab()) return;
    // The owning tab may have advanced, completed, or replaced this run.
    worker.reset(); syncAtIntro = false;
    if (storageAvailable) state = readStorage(storageKey);
    clock = null;
    if (intro) {
      intro.hidden = false;
      await restoreIntroduction();
      if (!state || state.format !== 4) tabGuard.release();
    } else if (state?.format === 4) await activate();
    else { tabGuard.release(); missing(); }
  } finally { retryingTab = false; }
}
async function claimTab() {
  if (!pageActive) return false;
  const generation = pageGeneration;
  const acquired = await tabGuard.acquire();
  if (!pageActive || generation !== pageGeneration) return false;
  if (!acquired) { blockedRetry = retryBlocked; showBlocked(); return false; }
  blockedRetry = null;
  root.querySelector('[data-tab-blocked]')?.remove();
  showAlert();
  return true;
}
function updateStatus() {
  releaseCompleted();
  if (!runActive || !state?.finishReason || state.mode !== 'assessment' || blockedRetry) {
    status.replaceChildren(); statusSignature = ''; return;
  }
  const failed = Boolean(worker.error || remoteConflict);
  const label = t(state.result?.confirmed ? 'resultSaved' : failed ? 'resultSaveFailed' : 'resultWaiting');
  const retryVisible = !worker.busy && failed;
  const signature = `${label}:${retryVisible}`;
  if (signature === statusSignature) { showAlert(); return; }
  statusSignature = signature;
  status.replaceChildren(view.status(label, retryVisible ? () => {
    if (remoteConflict) { document.querySelector('#player-conflict').showModal(); return; }
    worker.paused = false; worker.recover();
  } : null));
  showAlert();
}

function viewSignature() { return JSON.stringify([state.index, state.phase, state.finishReason, state.result, state.finishReason && hasPending(state), state.student]); }

// Recovery and uploads are silent here and never block deliberate fresh admission.
function restoreIntroduction() {
  if (restoring) return restoring;
  const task = prepareSavedRun();
  restoring = task;
  task.finally(() => { if (restoring === task) restoring = null; });
  return task;
}
async function prepareSavedRun() {
  if (!state || state.format !== 4) return;
  const generation = pageGeneration;
  const owned = tabGuard.allowed;
  if (!await claimTab()) return;
  const run = runGeneration;
  try {
    if (intro && !runActive) intro.hidden = false;
    // A tab that just obtained ownership must use the latest stored run.
    if (!owned && storageAvailable) { worker.reset(); state = readStorage(storageKey); }
    if (!state || state.format !== 4) return;
    syncAtIntro = true;
    clock = new PlayerClock(state);
    if (!state.finishReason && clock.now() >= Date.parse(state.expiresAt)) { finishTimed(state); persist(); }
    if (state.mode === 'assessment') {
      if (worker.paused) return;
      await worker.recover();
      if (!pageActive || generation !== pageGeneration || run !== runGeneration) return;
      if (worker.error || worker.paused) return;
    }
    await resumeIfActive();
  } catch (_) {
    // Inaccessible/unavailable old runs must not clutter or block fresh admission.
  } finally {
    if (generation === pageGeneration && run === runGeneration && !runActive) {
      if ((!state || !hasPending(state)) && !worker.busy) tabGuard.release();
      updateStatus(); showAlert();
    }
  }
}

async function resumeIfActive() {
  if (!pageActive || !syncAtIntro || starting || !state || blockedRetry) return;
  if (!state.finishReason && clock.now() >= Date.parse(state.expiresAt)) {
    finishTimed(state); persist(); worker.schedule(); return;
  }
  if (!state.finishReason && (state.mode === 'practice' || state.serverStatus === 'in_progress')) await activate(false);
}

function clearPreviousRun() {
  runGeneration++;
  worker.reset();
  clearInterval(timer); clearInterval(mediaTimer);
  state = null; clock = null; runActive = false; restoring = null; syncAtIntro = false;
  mediaBusy = false;
  for (const entry of blobs.values()) URL.revokeObjectURL(entry.url);
  blobs.clear(); blobPending.clear(); blobBytes = 0;
  for (const suffix of ['', ':admission', ':retry']) store(storageKey + suffix, null);
  admission = null; warnedDeadline = ''; renderedPhase = ''; statusSignature = ''; remoteConflict = null; lastError = '';
  announcer.textContent = ''; root.replaceChildren(); status.replaceChildren();
  document.querySelector('#player-conflict').close(); updateHeader(); showAlert();
}
async function activate(refresh = true, explicitResume = false) {
  if (!state || state.format !== 4 || state.quiz.shareToken !== config.shareToken) return missing();
  if (!pageActive) return;
  if (needsOwnership() && !await claimTab()) return;
  blockedRetry = null;
  runActive = true;
  syncAtIntro = false;
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
  runActive = false; updateHeader();
  // Preserve old work for support without inventing occurrence times.
  const backup = state?.quiz?.questions && state?.items
    ? JSON.stringify({title: state.quiz.title, questions: state.quiz.questions.map((q, i) => ({
      question: q.content, selected: state.items[i]?.answerCodes || [], text: state.items[i]?.textAnswer || ''
    }))}, null, 2)
    : null;
  root.replaceChildren(view.legacy(backup));
}
function missing() {
  runActive = false; updateHeader();
  root.replaceChildren(view.missing(config.quizUrl));
}

async function start(event) {
  event.preventDefault();
  if (starting) return;
  const form = event.currentTarget;
  if (!form.reportValidity()) return;
  const alreadyOwned = tabGuard.allowed;
  if (!await claimTab()) return;
  if (starting) return;
  if (!alreadyOwned && !runActive && storageAvailable) {
    worker.reset(); state = readStorage(storageKey); clock = null;
  }
  starting = true;
  startReservation = true;
  const generation = pageGeneration;
  let definitiveFailure = false;
  const startButton = form.querySelector('button[type="submit"]');
  const startLabel = startButton.querySelector('[data-start-label]');
  startButton.disabled = true; startLabel.textContent = t('starting'); lastError = ''; showAlert();
  form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
  try {
    // Only a pending admission with no previous run belongs to this new-start retry.
    const pendingAdmission = !state ? admission || readStorage(storageKey + ':admission') : null;
    clearPreviousRun();
    admission = pendingAdmission;
    if (admission) store(storageKey + ':admission', admission);
    if (!admission) {
      const ticket = await request(`/tickets/${config.shareToken}`);
      if (!pageActive || generation !== pageGeneration) return;
      admission = ticket.data; store(storageKey + ':admission', admission);
    }
    const body = {ticket: admission.ticket};
    if (config.mode === 'assessment') for (const [key, value] of new FormData(form)) body[key] = value;
    const response = await request('/starts', {method: 'POST', body});
    if (!pageActive || generation !== pageGeneration) return;
    state = createState(response.data, response.meta.timestamp);
    store(storageKey + ':admission', null); admission = null;
    startReservation = false;
    persist(); await activate(false);
  } catch (error) {
    if (!pageActive || generation !== pageGeneration) return;
    definitiveFailure = Boolean(error.status && error.status < 500);
    if (['quiz_changed', 'credential_expired', 'invalid_credential'].includes(error.code)) { admission = null; store(storageKey + ':admission', null); }
    lastError = !error.status || error.status >= 500 ? t('startFailed') : error.message;
    for (const key of Object.keys(error.fields || {})) form.elements.namedItem(key)?.setAttribute('aria-invalid', 'true');
    form.querySelector('[aria-invalid="true"]')?.focus();
    showAlert();
  } finally {
    if (generation !== pageGeneration) return;
    starting = false; startButton.disabled = false; startLabel.textContent = t('start');
    if (definitiveFailure) { startReservation = false; tabGuard.release(); }
    releaseCompleted();
  }
}

async function retryPractice() {
  if (starting || state?.mode !== 'practice' || !state.finishReason) return;
  if (!await claimTab()) return;
  if (starting) return;
  starting = true;
  startReservation = true;
  const generation = pageGeneration;
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
      if (!pageActive || generation !== pageGeneration) return;
      if (ticket.mode !== 'practice') {
        clearPreviousRun();
        window.location.assign(config.quizUrl);
        return;
      }
      pending = {fromStartedAt: state.startedAt, ticket: ticket.ticket};
      state.practiceRetry = pending; store(retryKey, pending); persist();
    }
    pending.needsRecovery = true;
    state.practiceRetry = pending; store(retryKey, pending); persist();
    const response = await request('/starts', {method: 'POST', body: {ticket: pending.ticket}});
    if (!pageActive || generation !== pageGeneration) return;
    const fresh = createState(response.data, response.meta.timestamp);
    clearPreviousRun();
    state = fresh;
    startReservation = false;
    warnedDeadline = ''; renderedPhase = ''; remoteConflict = null;
    announcer.textContent = '';
    persist(); store(retryKey, null);
    await activate(false);
  } catch (error) {
    if (!pageActive || generation !== pageGeneration) return;
    definitiveFailure = Boolean(error.status && error.status < 500);
    if (definitiveFailure && state.practiceRetry) {
      state.practiceRetry.needsRecovery = false; store(retryKey, state.practiceRetry); persist();
    }
    if (['quiz_changed', 'credential_expired', 'invalid_credential'].includes(error.code)) {
      delete state.practiceRetry; store(retryKey, null); persist();
    }
    lastError = error.message; showAlert();
  } finally {
    if (generation !== pageGeneration) return;
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

function quit() {
  if (!canRun() || !window.confirm(t('quitConfirm'))) return;
  quitQuiz(state, clock.now());
  persist(); changed(true); render(); tick();
}
headerQuit.addEventListener('click', quit);

function startAnotherAssessment() {
  if (!state || state.mode !== 'assessment' || !state.result?.confirmed || hasPending(state)) return;
  clearPreviousRun(); tabGuard.release();
  window.location.assign(config.quizUrl);
}

function render() {
  if (!state) return;
  renderedPhase = viewSignature();
  root.replaceChildren();
  history.replaceState(null, '', `${config.quizUrl}/${state.finishReason ? 'results' : 'play'}`);
  updateHeader();
  if (state.finishReason) renderResults(); else renderQuestion();
  updateStatus();
  root.querySelector('h1, h2')?.focus({preventScroll: true});
}

function studentIdentity() {
  return state.mode === 'assessment' && state.student?.name
    ? state.student.email ? `${state.student.name} · ${state.student.email}` : state.student.name
    : '';
}

function renderQuestion() {
  const question = state.quiz.questions[state.index];
  const item = state.items[state.index];
  root.append(view.question({
    title: state.quiz.title,
    identity: studentIdentity(),
    question,
    item,
    options: question.options.map((option, index) => ({...option, letter: optionLetter(index)})),
    deadlines: visibleDeadlines(state),
    monitored: state.mode === 'assessment' && state.quiz.settings.cheatCheck,
    fullscreen: fullscreenActive(),
    total: state.items.length,
    confirmedCount: state.items.filter(row => row.status === 'locked').length,
    locked: item.status === 'locked',
    last: state.index === state.items.length - 1,
    hint: question.type === 'short_text' ? 'textHint' : question.type === 'single_choice' ? 'singleHint' : 'multiHint',
  }, {
    valid: () => question.type === 'short_text' ? normalize(item.textAnswer) !== '' : item.answerCodes.length > 0,
    onEdit: answer => {
      if (!canRun()) return false;
      editAnswer(state, answer, clock.now());
      changed();
      return true;
    },
    onSubmit: () => submit(),
    onSkip: () => submit('skipped'),
    onNext: next,
    onFullscreen: enableFullscreen,
    media: renderMedia,
    feedback: () => state.quiz.settings.feedback === 'after_each' && !receiptOnly(state.quiz.settings)
      ? feedback(question, item, grade(question, item)) : null,
  }));
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
  const correctAnswers = [];
  if (settings.showAnswers) {
    if (question.type === 'short_text') correctAnswers.push({text: question.acceptedAnswers.join(' / ')});
    else question.options.forEach((option, index) => {
      if (question.correctCodes.includes(option.code)) correctAnswers.push({
        text: `${optionLetter(index)}) ${option.content || t('mediaImage')}`,
        key: `o:${option.id}`,
        media: option.media,
      });
    });
  }
  return view.feedback({
    verdict: result.result,
    answer: answerText(question, item),
    showAnswers: settings.showAnswers,
    correctLabel: question.type === 'short_text' ? 'acceptedAnswers' : 'correctAnswer',
    correctAnswers,
    explanation: settings.showAnswers && settings.showExplain ? question.explanation : '',
  }, renderMedia);
}

function renderResults() {
  const local = summarize(state.quiz.questions, state.items);
  const result = state.result || local;
  const scoreVisible = Boolean(state.quiz.settings.showScore && result.percent !== undefined);
  const parsedTotal = Number(result.maxScore);
  const total = Number.isFinite(parsedTotal) ? parsedTotal : state.quiz.questions.length;
  const restart = state.mode === 'practice' ? 'tryAgain' : state.result?.confirmed && !hasPending(state) ? 'startAnother' : null;
  root.append(view.results({
    title: state.quiz.title,
    identity: studentIdentity(),
    heading: state.finishReason === 'quit' ? 'quitComplete' : 'complete',
    emoji: scoreVisible ? completionEmoji(result.percent) : '🎉',
    scoreVisible,
    percent: result.percent,
    total,
    counts: scoreVisible ? resultCounts(result.items, total) : null,
    status: state.mode === 'practice' ? 'practiceResult' : null,
    scoreChanged: state.result && state.quiz.settings.showScore && state.result.score !== local.score,
    reason: state.finishReason !== 'completed' ? state.finishReason : null,
    restart,
    starting,
  }, state.mode === 'practice' ? retryPractice : startAnotherAssessment));
  if (receiptOnly(state.quiz.settings)) return;
  const awards = state.quiz.questions.map((question, index) =>
    result.items.find(row => row.questionId === question.id)
      || grade(question, state.items[index].answerStatus === 'answered' ? state.items[index] : {}));
  root.append(view.review(state.quiz.questions.map((question, index) => ({
    question: question.content, verdict: awards[index].result,
  })), (details, index) => {
    // Media and expanded feedback are loaded once, only when the review is opened.
    const question = state.quiz.questions[index];
    const media = renderMedia(`q:${question.id}`, question.media);
    if (media) details.append(media);
    details.append(feedback(question, state.items[index], awards[index]));
  }));
}

function tick() {
  if (!canRun() || !state || state.finishReason || !clock) return;
  const now = clock.now();
  for (const box of root.querySelectorAll('[data-deadline]')) {
    const remaining = Math.max(0, Math.ceil((Date.parse(state.expiresAt) - now) / 1000));
    box.querySelector('[data-slot="remaining"]').textContent = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
    box.classList.toggle('is-urgent', remaining <= 30);
  }
  const due = deadlines(state)[0];
  if (!due) return;
  if (due.reason !== 'stale_timeout' && due.at - now <= 30000 && warnedDeadline !== `${due.at}`) { warnedDeadline = `${due.at}`; notify(t('timeWarning')); }
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
function renderMedia(key, descriptor) {
  if (!descriptor) return null;
  const mediaState = state;
  const kind = descriptor.type === 'image' ? 'image' : descriptor.type === 'audio' ? 'audio'
    : directVideo(descriptor.embedUrl || descriptor.url) ? 'video' : 'embed';
  const wrapper = view.media(kind, key, blobs.get(key)?.url || descriptor.embedUrl || descriptor.url, wrapper => {
    view.mediaError(wrapper, async () => {
      try {
        await refreshMedia();
        if (state !== mediaState) return;
        const replacement = renderMedia(key, getMedia(key));
        if (replacement) wrapper.replaceWith(replacement);
        else wrapper.remove();
      } catch (error) { if (state === mediaState) { lastError = error.message; showAlert(); } }
    });
  });
  // Bounded in-memory image cache; never written to server/tab storage.
  if (descriptor.type === 'image' && !blobs.has(key) && !blobPending.has(key) && blobBytes < 32 * 1024 * 1024) {
    const originalState = state;
    blobPending.add(key);
    fetch(descriptor.url, {credentials: 'omit', referrerPolicy: 'no-referrer'}).then(response => { if (!response.ok) throw new Error(); return response.blob(); })
      .then(blob => { if (state === originalState && blobBytes + blob.size <= 32 * 1024 * 1024) { blobBytes += blob.size; blobs.set(key, {url: URL.createObjectURL(blob)}); } })
      .catch(() => {}).finally(() => { if (state === originalState) blobPending.delete(key); });
  }
  return wrapper;
}

async function refreshMedia() {
  if (!state || mediaBusy) return;
  if (!await claimTab()) return;
  if (mediaBusy) return;
  const originalState = state;
  const generation = runGeneration;
  mediaBusy = true;
  try {
    const path = state.mode === 'practice' ? '/practice/media' : `/assessments/${state.attemptId}/media`;
    const response = await request(path, {method: 'POST', body: {}, credential: state.credential});
    if (!pageActive || state !== originalState) return;
    if (response.data.credential) state.credential = response.data.credential;
    const incoming = new Map(response.data.quiz.questions.map(question => [question.id, question]));
    for (const question of state.quiz.questions) {
      question.media = incoming.get(question.id)?.media || null;
      const options = new Map((incoming.get(question.id)?.options || []).map(option => [option.id, option]));
      for (const option of question.options) option.media = options.get(option.id)?.media || null;
    }
    persist();
  } finally { if (generation === runGeneration) mediaBusy = false; releaseCompleted(); }
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
  worker.reset();
  state = createState({...remoteConflict, credential}, new Date(clock.now()).toISOString());
  worker.paused = false; remoteConflict = null; document.querySelector('#player-conflict').close(); persist(); activate();
});
window.addEventListener('online', () => {
  updateStatus();
  if (blockedRetry) { retryBlocked(); return; }
  if (intro && !runActive && state) restoreIntroduction(); else worker.flush();
});
window.addEventListener('offline', () => { connectionLost = true; updateStatus(); });
window.addEventListener('beforeunload', event => { persist(); if (runActive && state && hasPending(state)) { event.preventDefault(); event.returnValue = ''; } });
window.addEventListener('pagehide', () => {
  persist();
  resumeOnReturn = runActive || startReservation || Boolean(blockedRetry);
  pageActive = false; pageGeneration++; runActive = false;
  runGeneration++; worker.reset(); syncAtIntro = false;
  restoring = null; starting = false; startReservation = false;
  clearInterval(timer); clearInterval(mediaTimer);
  tabGuard.release();
});
window.addEventListener('pageshow', event => {
  if (!event.persisted) return;
  pageActive = true;
  if (storageAvailable) { state = readStorage(storageKey); clock = null; }
  if (blockedRetry) { retryBlocked(); return; }
  if (intro && !intro.hidden && state) restoreIntroduction();
  else if (resumeOnReturn && state) activate();
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
if (state && state.format !== 4 && config.page !== 'intro') { showLegacy(); }
else if (config.page === 'intro') {
  if (state?.format === 4) {
    restoreIntroduction();
  }
} else if (state?.format === 4) {
  activate();
} else missing();
showAlert();
