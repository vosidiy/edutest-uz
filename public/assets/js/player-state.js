export const uniqueKey = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
const copy = value => JSON.parse(JSON.stringify(value));
const sameAnswer = (a, b) => JSON.stringify([...(a.answerCodes || [])].sort()) === JSON.stringify([...(b.answerCodes || [])].sort()) && (a.textAnswer || '') === (b.textAnswer || '');

export function createState(data, serverTime, wallTime = Date.now()) {
  const serverItems = new Map((data.items || []).map(item => [item.questionId, item]));
  const items = data.quiz.questions.map((question, index) => {
    const saved = serverItems.get(question.id);
    return saved ? {...copy(saved), ackVer: saved.saveVer} : {questionId: question.id, status: index === 0 ? 'active' : 'pending',
      answerCodes: [], textAnswer: '', saveVer: 0, ackVer: 0, submitKey: null, reason: null, startedAt: index === 0 ? data.startedAt : null};
  });
  const firstActive = items.findIndex(item => item.status === 'active');
  const lastLocked = items.findLastIndex(item => item.status === 'locked');
  const finished = !!data.result;
  return {format: 1, mode: data.mode, attemptId: data.attemptId || null, credential: data.credential, version: data.version || 1,
    quiz: data.quiz, startedAt: data.startedAt, totalDueAt: data.totalDueAt, closeAt: data.closeAt,
    items, index: Math.max(0, firstActive >= 0 ? firstActive : lastLocked), phase: finished ? 'complete' : firstActive >= 0 ? 'answering' : 'feedback',
    finishReason: data.finishReason || null, result: data.result || null, events: [],
    clock: {server: Date.parse(serverTime), wall: wallTime}, lastClock: Date.parse(serverTime)};
}

export function editAnswer(state, answer) {
  const item = state.items[state.index];
  if (state.finishReason || item.status !== 'active') return false;
  Object.assign(item, answer, {saveVer: item.saveVer + 1});
  return true;
}

export function submitAnswer(state, reason = 'answered', key = uniqueKey()) {
  const item = state.items[state.index];
  if (state.finishReason || item.status !== 'active') return false;
  if (reason === 'skipped') Object.assign(item, {answerCodes: [], textAnswer: ''});
  Object.assign(item, {status: 'locked', reason, submitKey: key, saveVer: item.saveVer + 1});
  state.phase = 'feedback';
  return true;
}

export function nextQuestion(state, now) {
  if (state.finishReason || state.phase !== 'feedback') return false;
  if (state.index === state.items.length - 1) { state.finishReason = 'completed'; state.phase = 'complete'; return true; }
  state.index++;
  const item = state.items[state.index];
  if (item.status === 'pending') Object.assign(item, {status: 'active', startedAt: now, saveVer: 1});
  state.phase = item.status === 'locked' ? 'feedback' : 'answering';
  return true;
}

export function finishTimed(state, reason, key = uniqueKey()) {
  if (state.finishReason) return false;
  if (state.items[state.index].status === 'active') submitAnswer(state, 'attempt_timeout', key);
  state.finishReason = reason;
  state.phase = 'complete';
  return true;
}

export function deadlines(state) {
  const values = [];
  if (state.totalDueAt) values.push({at: Date.parse(state.totalDueAt), reason: 'total_timeout', label: 'quizTimer'});
  if (state.closeAt) values.push({at: Date.parse(state.closeAt), reason: 'scheduled_close', label: 'closingTimer'});
  const question = state.quiz.questions[state.index];
  const item = state.items[state.index];
  if (item.status === 'active' && question.timeLimitSec != null) values.push({at: Date.parse(item.startedAt) + question.timeLimitSec * 1000, reason: 'question_timeout', label: 'questionTimer'});
  // An overall timeout wins ties with the question timer.
  return values.sort((a, b) => a.at - b.at);
}

export function syncPayload(state) {
  const dirty = state.items.filter(item => item.saveVer > item.ackVer);
  return {version: state.version, items: dirty.slice(0, 100).map(({questionId, answerCodes, textAnswer, saveVer, submitKey, reason, startedAt}) =>
    ({questionId, answerCodes: [...answerCodes].sort(), textAnswer, saveVer, submitKey, reason: reason || 'answered', startedAt})),
    finishReason: dirty.length <= 100 ? state.finishReason : null};
}

export function hasPending(state) {
  return state.mode === 'assessment' && (state.items.some(item => item.saveVer > item.ackVer) || (state.finishReason && !state.result) || state.events.length > 0);
}

/** Merge acknowledgements without replacing newer local work. Locked conflicts require a human choice. */
export function mergeServer(state, server) {
  if (server.version < state.version) return true;
  const localById = new Map(state.items.map(item => [item.questionId, item]));
  for (const remote of server.items) {
    const local = localById.get(remote.questionId);
    if (!local) throw new Error('Question membership changed');
    const dirty = local.saveVer > local.ackVer;
    if (remote.status === 'locked' && dirty && (!sameAnswer(local, remote) || (local.submitKey && local.submitKey !== remote.submitKey))) return false;
  }
  for (const remote of server.items) {
    const local = localById.get(remote.questionId);
    const dirty = local.saveVer > local.ackVer;
    if (remote.status === 'locked' || !dirty) Object.assign(local, copy(remote));
    else if (remote.saveVer >= local.saveVer && (!sameAnswer(local, remote) || (local.submitKey && remote.status !== 'locked'))) local.saveVer = remote.saveVer + 1;
    if (remote.startedAt) local.startedAt = remote.startedAt;
    local.ackVer = remote.saveVer;
  }
  state.version = server.version;
  state.result = server.result;
  if (server.result) { state.finishReason = server.finishReason; state.phase = 'complete'; }
  // If another tab advanced beyond this tab, resume its current position without going backwards.
  while (!state.finishReason && state.items[state.index].status === 'locked' && state.index < state.items.length - 1 && state.items[state.index + 1].startedAt) state.index++;
  if (!state.finishReason) state.phase = state.items[state.index].status === 'locked' ? 'feedback' : 'answering';
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
    this.state.lastClock = value;
    return value;
  }
  iso() { return new Date(Math.ceil(this.now()) + 1).toISOString(); }
}
