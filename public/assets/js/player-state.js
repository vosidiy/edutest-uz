export const uniqueKey = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
const copy = value => JSON.parse(JSON.stringify(value));
const iso = time => new Date(Math.ceil(time)).toISOString();
const time = value => Date.parse(value);
export const receiptOnly = settings => !settings.showScore && !settings.showAnswers && !settings.showExplain;
const firstOpen = items => Math.max(0, items.findIndex(item => item.answerStatus === 'not_reached'));

export function createState(data, serverTime, wallTime = Date.now()) {
  const serverItems = new Map((data.items || []).map(item => [item.questionId, item]));
  const items = data.quiz.questions.map((question, index) => {
    const saved = serverItems.get(question.id);
    return saved ? copy(saved) : {questionId: question.id, status: index === 0 ? 'active' : 'pending', answerStatus: 'not_reached', answerCodes: [], textAnswer: ''};
  });
  const finished = data.status === 'completed';
  const allConfirmed = items.length > 0 && items.every(item => item.answerStatus !== 'not_reached');
  const index = finished || allConfirmed ? Math.max(0, items.length - 1) : firstOpen(items);
  if (!finished && items[index]?.answerStatus === 'not_reached') items[index].status = 'active';
  return {
    format: 4, mode: data.mode, attemptId: data.attemptId || null, credential: data.credential,
    quiz: data.quiz, student: data.student || null, startedAt: data.startedAt, expiresAt: data.expiresAt, deadlineReason: data.deadlineReason,
    clientActivityAt: data.clientActivityAt || data.startedAt, activityPending: false, activitySentAt: 0,
    serverStatus: data.status, lateSync: Boolean(data.lateSync),
    items, index, phase: finished ? 'complete' : allConfirmed ? 'feedback' : 'answering', pendingAnswers: [], finishPending: false, finishRecord: null,
    finishReason: finished ? data.finishReason : null, result: finished ? data.result : null, serverResult: data.result || null, events: [],
    clock: {server: time(serverTime), wall: wallTime}, lastClock: time(serverTime),
  };
}

export function deadlines(state) {
  if (!state.expiresAt) return [];
  const labels = {timer_expired: 'quizTimer', scheduled_close: 'closingTimer'};
  return [{at: time(state.expiresAt), reason: state.deadlineReason, label: labels[state.deadlineReason] || 'quizTimer'}];
}

// Inactivity remains an enforced deadline, but it is an internal recovery rule rather than a quiz timer.
export function visibleDeadlines(state) {
  return deadlines(state).filter(deadline => deadline.reason !== 'stale_timeout');
}

function setFinish(state, reason, at) {
  state.finishReason = reason; state.finishPending = state.mode === 'assessment'; state.phase = 'complete';
  state.finishRecord = {finishReason: reason, clientFinishedAt: at, clientActivityAt: state.clientActivityAt,
    confirmedCount: state.items.filter(item => item.answerStatus !== 'not_reached').length};
  state.activityPending = false;
}

export function finishTimed(state, reason = state.deadlineReason) {
  if (state.finishReason) return false;
  setFinish(state, reason, state.expiresAt);
  return true;
}

export function quitQuiz(state, now = state.lastClock) {
  if (expireIfDue(state, now) || state.finishReason) return false;
  recordActivity(state, now);
  setFinish(state, 'quit', state.clientActivityAt);
  return true;
}

export function expireIfDue(state, now = state.lastClock) {
  if (!state.finishReason && state.expiresAt && now >= time(state.expiresAt)) return finishTimed(state);
  return false;
}

export function recordActivity(state, now = state.lastClock) {
  if (expireIfDue(state, now) || state.finishReason) return false;
  state.clientActivityAt = iso(Math.max(now, time(state.clientActivityAt)));
  if (state.quiz.settings.timeLimitSec === null) {
    let deadline = time(state.clientActivityAt) + 8 * 60 * 60 * 1000;
    state.deadlineReason = 'stale_timeout';
    const closes = time(state.quiz.settings.closesAt);
    if (Number.isFinite(closes) && closes < deadline) { deadline = closes; state.deadlineReason = 'scheduled_close'; }
    state.expiresAt = iso(deadline);
    state.activityPending = state.mode === 'assessment';
  }
  return true;
}

export function editAnswer(state, answer, now = state.lastClock) {
  if (expireIfDue(state, now) || state.finishReason || state.items[state.index].status !== 'active') return false;
  recordActivity(state, now);
  Object.assign(state.items[state.index], answer);
  return true;
}

export function submitAnswer(state, reason = 'answered', now = state.lastClock) {
  const item = state.items[state.index];
  if (expireIfDue(state, now) || state.finishReason || item.status !== 'active') return false;
  recordActivity(state, now);
  if (reason === 'skipped') Object.assign(item, {answerCodes: [], textAnswer: ''});
  item.status = 'locked';
  item.answerStatus = reason === 'skipped' ? 'skipped' : 'answered';
  item.clientAnsweredAt = state.clientActivityAt;
  if (state.mode === 'assessment') state.pendingAnswers.push({
    questionId: item.questionId, status: item.answerStatus, answerCodes: [...item.answerCodes].sort(), textAnswer: item.textAnswer || '',
    clientAnsweredAt: item.clientAnsweredAt, clientActivityAt: state.clientActivityAt,
  });
  state.phase = 'feedback';
  return true;
}

export function nextQuestion(state, now = state.lastClock) {
  if (expireIfDue(state, now) || state.finishReason || state.phase !== 'feedback') return false;
  recordActivity(state, now);
  if (state.index === state.items.length - 1) { setFinish(state, 'completed', state.clientActivityAt); return true; }
  state.index++;
  const item = state.items[state.index];
  if (item.status === 'pending') item.status = 'active';
  state.phase = item.status === 'locked' ? 'feedback' : 'answering';
  return true;
}

export function hasPending(state) {
  if (state.format !== 4) return state.mode === 'assessment'; // Preserve unsupported work on exit, too.
  return state.mode === 'assessment' && (state.pendingAnswers.length > 0 || state.finishPending || state.events.length > 0 || state.activityPending);
}
export function hasUploadPending(state) {
  return state?.mode === 'assessment' && (state.pendingAnswers.length > 0 || state.finishPending);
}
export function isFullscreenExit(previouslyActive, currentlyActive) { return Boolean(previouslyActive) && !currentlyActive; }

function sameAnswer(item, operation) {
  return JSON.stringify([...(item.answerCodes || [])].sort()) === JSON.stringify([...(operation.answerCodes || [])].sort())
    && (item.textAnswer || '') === (operation.textAnswer || '') && item.answerStatus === operation.status;
}

/** Small acknowledgements never replace local question state or drafts. */
export function acknowledge(state, ack, operation = null) {
  if (ack.attemptId !== state.attemptId) throw new Error('Attempt membership changed');
  if (operation) {
    if (ack.questionId !== operation.questionId || ack.answerStatus !== operation.status || state.pendingAnswers[0] !== operation) throw new Error('Invalid answer acknowledgement');
    state.pendingAnswers.shift();
  }
  state.serverStatus = ack.status; state.lateSync ||= Boolean(ack.lateSync);
  if (ack.clientActivityAt && time(ack.clientActivityAt) >= time(state.clientActivityAt)) {
    state.activityPending = false;
    state.expiresAt = ack.expiresAt; state.deadlineReason = ack.deadlineReason;
  }
  // Only an explicit Finish acknowledgement can seal the local run.
  if (ack.result?.confirmed) {
    if (state.pendingAnswers.length) throw new Error('Completion preceded answer acknowledgements');
    state.result = ack.result; state.finishPending = false; state.finishReason = ack.finishReason; state.phase = 'complete';
  }
}

/** Resume/recovery merges saved confirmations while preserving local drafts and unfinished uploads. */
export function mergeServer(state, server) {
  if (server.attemptId !== state.attemptId) return false;
  const remote = new Map(server.items.map(item => [item.questionId, item]));
  const pending = new Map(state.pendingAnswers.map(operation => [operation.questionId, operation]));
  const items = [];
  for (const local of state.items) {
    const saved = remote.get(local.questionId);
    if (!saved) return false;
    const operation = pending.get(local.questionId);
    if (saved.answerStatus !== 'not_reached') {
      if (operation && (!sameAnswer(saved, operation) || (saved.clientAnsweredAt && time(saved.clientAnsweredAt) !== time(operation.clientAnsweredAt)))) return false;
      items.push(copy(saved));
    } else {
      if (server.status === 'completed' && operation) return false;
      items.push(copy(local));
    }
  }
  if (server.status === 'completed' && state.finishRecord
      && (server.finishReason !== state.finishRecord.finishReason || time(server.result.finishedAt) !== time(state.finishRecord.clientFinishedAt))) return false;
  state.items = items;
  state.pendingAnswers = state.pendingAnswers.filter(operation => remote.get(operation.questionId).answerStatus === 'not_reached');
  for (const item of items) {
    if (item.answerStatus !== 'not_reached' && item.clientAnsweredAt && remote.get(item.questionId).answerStatus === 'not_reached'
        && !state.pendingAnswers.some(operation => operation.questionId === item.questionId)) {
      state.pendingAnswers.push({questionId:item.questionId, status:item.answerStatus, answerCodes:[...item.answerCodes].sort(),
        textAnswer:item.textAnswer || '', clientAnsweredAt:item.clientAnsweredAt, clientActivityAt:item.clientAnsweredAt});
    }
  }
  const positions = new Map(items.map((item,index) => [item.questionId,index]));
  state.pendingAnswers.sort((a,b) => positions.get(a.questionId) - positions.get(b.questionId));
  state.serverStatus = server.status; state.serverResult = server.result; state.lateSync ||= Boolean(server.lateSync);
  if (server.student) state.student = copy(server.student);
  if (time(server.clientActivityAt) >= time(state.clientActivityAt)) {
    state.activityPending = false; state.clientActivityAt = server.clientActivityAt;
    state.expiresAt = server.expiresAt; state.deadlineReason = server.deadlineReason;
  }
  if (server.status === 'completed') {
    state.result = server.result; state.finishReason = server.finishReason; state.finishPending = false; state.activityPending = false; state.phase = 'complete';
  } else if (!state.finishReason && state.phase !== 'feedback') {
    state.index = firstOpen(state.items);
    if (state.items[state.index].answerStatus === 'not_reached') state.items[state.index].status = 'active';
    else {
      // All answers may be received while the Finish response was lost.
      state.index = state.items.length - 1; state.phase = 'feedback';
    }
  }
  return true;
}

export class PlayerClock {
  constructor(state, monotonic = () => performance.now(), wall = () => Date.now()) {
    this.state = state; this.monotonic = monotonic; this.wall = wall;
    this.base = Math.max(state.lastClock || 0, state.clock.server + Math.max(0, wall() - state.clock.wall));
    this.mark = monotonic();
  }
  now() {
    const value = Math.max(this.base + this.monotonic() - this.mark, this.state.clock.server + this.wall() - this.state.clock.wall, this.state.lastClock || 0);
    this.state.lastClock = value; return value;
  }
  iso() { return iso(this.now()); }
}
