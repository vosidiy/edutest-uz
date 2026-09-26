import {grade, normalize, summarize} from './player-scoring.js';
import {createState, PlayerClock, editAnswer, submitAnswer, nextQuestion, finishTimed, deadlines, hasPending, uniqueKey, mergeServer} from './player-state.js';
import {PlayerSync} from './player-sync.js';

const config = JSON.parse(document.querySelector('#player-config').textContent);
const ui = config.ui;
const root = document.querySelector('#player-root');
const status = document.querySelector('#player-status');
const alert = document.querySelector('#player-alert');
const intro = document.querySelector('#quiz-introduction');
const announcer = document.querySelector('#player-announcer');
const storageKey = `edutest:player:${config.shareToken}`;
let state = null, clock = null, storageAvailable = true, lastError = '', timer = null, mediaTimer = null, remoteConflict = null;
let renderedPhase = '', warnedDeadline = '', starting = false, admission = null, mediaBusy = false;
let blobBytes = 0;
const blobs = new Map();
const blobPending = new Set();
const t = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`{${name}}`, String(value)), ui[key] || key);
const node = (tag, className = '', text = null) => { const element = document.createElement(tag); element.className = className; if (text !== null) element.textContent = text; return element; };
const button = (label, action, variant = 'btn-primary') => { const item = node('button', `btn ${variant}`, label); item.type = 'button'; item.addEventListener('click', action); return item; };
const notify = text => { announcer.textContent = ''; requestAnimationFrame(() => { announcer.textContent = text; }); };

function readStorage(key) {
  try { return JSON.parse(sessionStorage.getItem(key) || 'null'); }
  catch (_) { storageAvailable = false; return null; }
}
function store(key, value) {
  try { sessionStorage.setItem(key, JSON.stringify(value)); }
  catch (_) { storageAvailable = false; showAlert(); }
}
function persist() { if (state) { clock?.now(); store(storageKey, state); } }
function showAlert() {
  const messages = [];
  if (!storageAvailable) messages.push(t('storageUnavailable'));
  if (!navigator.onLine && state) messages.push(t('offline'));
  if (lastError) messages.push(lastError);
  alert.textContent = messages.join(' ');
  alert.hidden = !messages.length;
}

async function request(path, {method = 'GET', body, credential} = {}) {
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
    return value;
  } catch (error) {
    if (!error.status) error.message = t('networkError');
    throw error;
  } finally { clearTimeout(timeout); }
}

const worker = new PlayerSync(() => state, request, {
  persist,
  error(error) { lastError = error.message; showAlert(); },
  conflict(server) { remoteConflict = server; document.querySelector('#player-conflict').showModal(); },
  change() {
    updateStatus();
    if (state && (renderedPhase !== viewSignature())) render();
  }
});

function updateStatus() {
  if (!state) return;
  status.replaceChildren();
  let label = state.mode === 'practice' ? t('practiceNotice') : worker.busy ? t('saving') : hasPending(state) ? t('pending') : t('saved');
  status.append(node('span', '', label));
  if (state.mode === 'assessment' && hasPending(state) && !worker.busy) status.append(button(t('retry'), () => { lastError = ''; worker.paused = false; showAlert(); worker.flush(); }, 'btn-default btn-sm'));
  showAlert();
}

function viewSignature() { return `${state.index}:${state.phase}:${Boolean(state.finishReason)}:${Boolean(state.result)}`; }
function activate() {
  if (!state || state.format !== 1 || state.quiz.shareToken !== config.shareToken) return missing();
  if (intro) intro.hidden = true;
  clock = new PlayerClock(state);
  lastError = '';
  render();
  clearInterval(timer); timer = setInterval(tick, 250);
  clearInterval(mediaTimer); mediaTimer = setInterval(() => { if (navigator.onLine) refreshMedia().catch(() => {}); }, 240000);
  tick(); worker.flush(); setupIntegrity();
}
function missing() {
  root.replaceChildren(node('p', 'card player-error', t('missingState')));
  const link = node('a', 'btn btn-primary', t('introduction')); link.href = config.quizUrl; root.append(link);
}

async function start(event) {
  event.preventDefault();
  if (starting) return;
  if (state?.format === 1 && (!state.finishReason || hasPending(state))) { activate(); return; }
  const form = event.currentTarget;
  if (!form.reportValidity()) return;
  starting = true;
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
        persist(); activate(); return;
      } catch (error) { if (error.status !== 401) throw error; }
    }
    if (!admission) { admission = (await request(`/tickets/${config.shareToken}`)).data; store(storageKey + ':admission', admission); }
    const body = {ticket: admission.ticket};
    if (config.mode === 'assessment') for (const [key, value] of new FormData(form)) body[key] = value;
    const response = await request('/starts', {method: 'POST', body});
    state = createState(response.data, response.meta.timestamp);
    store(storageKey + ':admission', null); admission = null;
    persist(); activate();
  } catch (error) {
    if (['quiz_changed', 'credential_expired'].includes(error.code)) { admission = null; store(storageKey + ':admission', null); }
    lastError = error.message;
    for (const key of Object.keys(error.fields || {})) form.elements.namedItem(key)?.setAttribute('aria-invalid', 'true');
    form.querySelector('[aria-invalid="true"]')?.focus();
    showAlert();
  } finally { starting = false; startButton.disabled = false; startButton.textContent = t('start'); }
}

async function retryPractice() {
  if (starting || state?.mode !== 'practice' || !state.finishReason) return;
  starting = true;
  const retryButton = root.querySelector('[data-try-again]');
  retryButton.disabled = true; retryButton.textContent = t('starting');
  lastError = ''; showAlert();
  const retryKey = storageKey + ':retry';
  try {
    // Bind pending admission to the completed run so refresh/lost acknowledgements reuse it.
    let pending = state.practiceRetry || readStorage(retryKey);
    if (!pending || pending.fromStartedAt !== state.startedAt) {
      const ticket = (await request(`/tickets/${config.shareToken}`)).data;
      if (ticket.mode !== 'practice') throw new Error(t('practiceChanged'));
      pending = {fromStartedAt: state.startedAt, ticket: ticket.ticket};
      state.practiceRetry = pending; store(retryKey, pending); persist();
    }
    const response = await request('/starts', {method: 'POST', body: {ticket: pending.ticket}});
    const fresh = createState(response.data, response.meta.timestamp);
    state = fresh;
    warnedDeadline = ''; renderedPhase = ''; remoteConflict = null;
    announcer.textContent = '';
    persist(); store(retryKey, null);
    activate();
  } catch (error) {
    if (['quiz_changed', 'credential_expired', 'invalid_credential'].includes(error.code)) {
      delete state.practiceRetry; store(retryKey, null); persist();
    }
    lastError = error.message; showAlert();
  } finally {
    starting = false;
    retryButton.disabled = false; retryButton.textContent = t('tryAgain');
    // A recovered start may already have timed out and rendered a new results button.
    const visibleRetry = root.querySelector('[data-try-again]');
    if (visibleRetry) { visibleRetry.disabled = false; visibleRetry.textContent = t('tryAgain'); }
  }
}

function changed(immediate = false) { persist(); updateStatus(); if (state.mode === 'assessment') worker.schedule(immediate ? 0 : 400); }
function submit(reason = 'answered') {
  if (!submitAnswer(state, reason)) return;
  const result = grade(state.quiz.questions[state.index], state.items[state.index]);
  if (state.quiz.settings.feedback === 'at_end') nextQuestion(state, clock.iso());
  else notify(t(result.result));
  changed(true); render(); tick();
}
function next() { if (nextQuestion(state, clock.iso())) { changed(true); render(); tick(); } }

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
  top.append(node('p', 'player-quiz-title', state.quiz.title), node('div', 'player-timers'));
  root.append(top);
  for (const deadline of deadlines(state)) {
    const box = node('div', 'player-timer'); box.dataset.deadline = String(deadline.at); box.setAttribute('aria-live', 'off');
    box.append(node('span', '', t(deadline.label)), node('strong', '', ''));
    top.lastChild.append(box);
  }
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
    input.addEventListener('input', () => { editAnswer(state, {textAnswer: input.value}); submitButton.disabled = !valid(); changed(); });
    answers.append(input);
  } else {
    for (const option of question.options) {
      const label = node('label', `player-option${item.answerCodes.includes(option.code) ? ' is-selected' : ''}`);
      const input = node('input'); input.type = question.type === 'single_choice' ? 'radio' : 'checkbox'; input.name = 'answer'; input.value = option.code; input.checked = item.answerCodes.includes(option.code);
      const copy = node('span'); copy.append(node('span', 'player-option-copy', option.content)); appendMedia(copy, `o:${option.id}`, option.media);
      // Media-only choices still need an accessible option label.
      input.setAttribute('aria-label', option.content || `${t('yourAnswer')} ${question.options.indexOf(option) + 1}`);
      input.addEventListener('change', () => {
        const selected = [...answers.querySelectorAll('input:checked')].map(element => element.value);
        editAnswer(state, {answerCodes: selected});
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
    if (state.quiz.settings.feedback === 'after_each') paper.append(feedback(question, item, grade(question, item)));
    const actions = node('div', 'player-actions'); actions.append(button(t(state.index === state.items.length - 1 ? 'finish' : 'next'), next, 'btn-primary btn-lg')); paper.append(actions);
  }
  root.append(paper);
  const help = node('p', 'player-help', t('questionHint')); root.append(help);
}

function answerText(question, item) {
  return question.type === 'short_text' ? item.textAnswer || t('unanswered') : question.options.filter(option => item.answerCodes.includes(option.code)).map(option => option.content || t('mediaImage')).join('; ') || t('unanswered');
}
function feedback(question, item, result) {
  const settings = state.quiz.settings;
  const box = node('section', `player-feedback ${result.result}`);
  box.append(node('h3', '', t(result.result)), node('p', '', `${t('yourAnswer')}: ${answerText(question, item)}`));
  if (settings.showScore) box.append(node('p', '', `${result.points} / ${question.points} ${t('points')}`));
  if (settings.showAnswers) {
    box.append(node('strong', '', t(question.type === 'short_text' ? 'acceptedAnswers' : 'correctAnswer')));
    if (question.type === 'short_text') box.append(node('p', '', question.acceptedAnswers.join(' / ')));
    else for (const option of question.options.filter(option => question.correctCodes.includes(option.code))) {
      const answer = node('p', '', option.content); box.append(answer); appendMedia(box, `o:${option.id}`, option.media);
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
  if (state.quiz.settings.showScore) card.append(node('div', 'player-score', `${result.percent}%`), node('p', '', `${result.score} / ${result.maxScore} ${t('points')}`));
  else card.append(node('p', '', t('hiddenScore')));
  card.append(node('p', 'player-help', t(state.mode === 'practice' ? 'practiceResult' : state.result ? 'confirmed' : 'provisional')));
  if (state.result?.lateSync) card.append(node('p', 'alert alert-warning', t('lateSync')));
  if (state.result && state.quiz.settings.showScore && state.result.score !== local.score) card.append(node('p', 'alert alert-warning', t('scoreChanged')));
  if (state.finishReason !== 'completed') card.append(node('p', '', t(state.finishReason)));
  if (state.mode === 'practice') {
    const actions = node('div', 'player-actions');
    const retryButton = button(t('tryAgain'), retryPractice, 'btn-primary btn-lg');
    retryButton.dataset.tryAgain = ''; retryButton.disabled = starting;
    actions.append(retryButton); card.append(actions);
  }
  root.append(card);
  const review = node('section', 'player-review'); review.append(node('h2', '', t('review')));
  state.quiz.questions.forEach((question, index) => {
    const item = state.items[index];
    const award = result.items.find(row => row.questionId === question.id) || grade(question, item);
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
  if (!state || state.finishReason || !clock) return;
  const now = clock.now();
  for (const box of root.querySelectorAll('[data-deadline]')) {
    const remaining = Math.max(0, Math.ceil((Number(box.dataset.deadline) - now) / 1000));
    box.querySelector('strong').textContent = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
    box.classList.toggle('is-urgent', remaining <= 30);
  }
  const due = deadlines(state)[0];
  if (!due) return;
  if (due.at - now <= 30000 && warnedDeadline !== `${due.at}`) { warnedDeadline = `${due.at}`; notify(t('timeWarning')); }
  if (now < due.at) return;
  if (due.reason === 'question_timeout') { submit('question_timeout'); notify(t('questionTimeout')); }
  else { finishTimed(state, due.reason); changed(true); render(); notify(t(due.reason)); }
}

function getMedia(key) {
  const [kind, id] = key.split(':');
  if (kind === 'q') return state.quiz.questions.find(question => question.id === id)?.media;
  return state.quiz.questions.flatMap(question => question.options).find(option => option.id === id)?.media;
}
function appendMedia(parent, key, descriptor) {
  if (!descriptor) return;
  const wrapper = node('div', 'player-media'); wrapper.dataset.mediaKey = key;
  let media;
  if (descriptor.type === 'image') { media = node('img'); media.alt = t('mediaImage'); }
  else if (descriptor.type === 'audio') { media = node('audio'); media.controls = true; media.preload = 'metadata'; }
  else if (new URL(descriptor.url).pathname.toLowerCase().endsWith('.mp4')) { media = node('video'); media.controls = true; media.preload = 'metadata'; }
  else { media = node('iframe'); media.title = t('mediaVideo'); media.allow = 'fullscreen; picture-in-picture'; media.referrerPolicy = 'no-referrer'; media.setAttribute('allowfullscreen', ''); }
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
  } finally { mediaBusy = false; }
}

let integrityReady = false, lastActivity = 0, inactive = false;
function setupIntegrity() {
  if (integrityReady || state.mode !== 'assessment' || !state.quiz.settings.cheatCheck) return;
  integrityReady = true; lastActivity = clock.now();
  const record = (type, durationMs = null) => {
    if (state.finishReason) return;
    // Bound tab storage during very long/disconnected sessions; events are signals, not surveillance.
    if (state.events.length >= 1000) return;
    state.events.push({key: uniqueKey(), type, happenedAt: clock.iso(), durationMs}); changed();
  };
  document.addEventListener('visibilitychange', () => { record(document.hidden ? 'tab_hidden' : 'tab_visible'); tick(); });
  window.addEventListener('blur', () => record('window_blur'));
  window.addEventListener('focus', () => { record('window_focus'); tick(); });
  document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) record('fullscreen_exit'); });
  const activity = () => { const now = clock.now(); if (inactive) { record('inactivity_end', Math.min(4294967295, Math.floor(now - lastActivity))); inactive = false; } lastActivity = now; };
  for (const name of ['keydown', 'pointerdown', 'pointermove']) document.addEventListener(name, activity, {passive: true});
  setInterval(() => { if (!inactive && clock.now() - lastActivity >= 60000) { inactive = true; record('inactivity_start'); } }, 5000);
}

document.querySelector('#keep-local').addEventListener('click', () => document.querySelector('#player-conflict').close());
document.querySelector('#use-server').addEventListener('click', () => {
  if (!remoteConflict) return;
  const credential = state.credential;
  state = createState({...remoteConflict, credential}, new Date(clock.now()).toISOString());
  worker.paused = false; remoteConflict = null; document.querySelector('#player-conflict').close(); persist(); activate();
});
window.addEventListener('online', () => { lastError = ''; updateStatus(); worker.flush(); });
window.addEventListener('offline', updateStatus);
window.addEventListener('beforeunload', event => { persist(); if (state && hasPending(state)) { event.preventDefault(); event.returnValue = ''; } });
window.addEventListener('pagehide', persist);
document.querySelectorAll('.player-schedule time').forEach(element => { element.textContent = new Intl.DateTimeFormat(undefined, {dateStyle: 'medium', timeStyle: 'short'}).format(new Date(element.dateTime)); });
const admissionForm = document.querySelector('#quiz-admission');
if (admissionForm) {
  admissionForm.addEventListener('submit', start);
  admissionForm.querySelector('button[type="submit"]').disabled = false;
}

state = readStorage(storageKey);
if (config.page === 'intro') {
  const resume = document.querySelector('#resume-quiz');
  if (state?.format === 1) { resume.hidden = false; resume.addEventListener('click', activate); }
} else if (state?.format === 1) {
  activate();
  if (state.mode === 'assessment') request(`/assessments/${state.attemptId}`, {credential: state.credential}).then(response => {
    if (!mergeServer(state, response.data)) { worker.paused = true; remoteConflict = response.data; document.querySelector('#player-conflict').showModal(); }
    persist(); render(); tick(); worker.flush();
  }).catch(error => { lastError = error.message; showAlert(); });
} else missing();
showAlert();
