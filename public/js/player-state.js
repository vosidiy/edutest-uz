const copy = value => JSON.parse(JSON.stringify(value));
const time = value => Date.parse(value);
const nowIso = value => new Date(value).toISOString();

export const receiptOnly = settings => !settings.showScore && !settings.showAnswers && !settings.showExplain;

function browserQuiz(snapshot) {
  const settings = snapshot.quiz;
  return {
    title: settings.title,
    description: settings.description,
    shareToken: settings.shareToken,
    cover: snapshot.cover,
    settings: copy(settings),
    questions: snapshot.questions.map(question => ({
      ...copy(question),
      type: 'single_choice',
      correctCodes: [question.correctCode],
    })),
  };
}

function itemsFrom(quiz, answers) {
  return quiz.questions.map((question, index) => {
    const answer = answers[index];
    return {
      questionId: question.id,
      status: answer ? 'locked' : (index === answers.length ? 'active' : 'pending'),
      answerStatus: answer ? (answer.answerCode === null ? 'skipped' : 'answered') : 'not_reached',
      answerCodes: answer?.answerCode === null || answer === undefined ? [] : [answer.answerCode],
      textAnswer: '',
      clientAnsweredAt: answer?.answeredAt ?? null,
    };
  });
}

export function createState(data) {
  const quiz = browserQuiz(data.quiz);
  const answers = copy(data.answers || []);
  const terminal = data.status !== 'in_progress';
  return {
    format: 5,
    shareToken: quiz.shareToken,
    attemptId: data.attemptId,
    credential: data.credential,
    quiz,
    student: copy(data.student || null),
    startedAt: data.startedAt,
    expiresAt: data.expiresAt,
    answers,
    answerVersion: Number(data.answerVersion || 0),
    serverVersion: Number(data.answerVersion || 0),
    items: itemsFrom(quiz, answers),
    index: terminal ? Math.max(0, quiz.questions.length - 1) : Math.min(answers.length, Math.max(0, quiz.questions.length - 1)),
    phase: terminal ? 'complete' : 'answering',
    status: data.status,
    finishReason: data.finishReason || null,
    result: data.result || null,
    lateSync: Boolean(data.lateSync),
    progressPending: false,
    finishPending: false,
    finishRecord: null,
    draftCode: null,
    clock: {server: Date.now(), wall: Date.now()},
    lastClock: Date.now(),
  };
}

export function deadlines(state) {
  return [{at: time(state.expiresAt), reason: 'expired', label: 'quizTimer'}];
}
export const visibleDeadlines = deadlines;

export function editAnswer(state, answerCode, now = state.lastClock) {
  if (state.finishReason || expireIfDue(state, now) || state.phase !== 'answering') return false;
  const question = state.quiz.questions[state.index];
  if (answerCode !== null && !question.options.some(option => option.code === answerCode)) return false;
  state.draftCode = answerCode;
  state.items[state.index].answerCodes = answerCode === null ? [] : [answerCode];
  return true;
}

export function submitAnswer(state, skipped = false, now = state.lastClock) {
  if (state.finishReason || expireIfDue(state, now) || state.phase !== 'answering') return false;
  if (!skipped && state.draftCode === null) return false;
  const answerCode = skipped ? null : state.draftCode;
  const answeredAt = nowIso(now);
  state.answers.push({questionId: state.quiz.questions[state.index].id, answerCode, answeredAt});
  state.answerVersion++;
  state.progressPending = true;
  const item = state.items[state.index];
  item.status = 'locked';
  item.answerStatus = answerCode === null ? 'skipped' : 'answered';
  item.answerCodes = answerCode === null ? [] : [answerCode];
  item.clientAnsweredAt = answeredAt;
  state.phase = 'feedback';
  state.draftCode = null;
  return true;
}

export function nextQuestion(state, now = state.lastClock) {
  if (state.finishReason || expireIfDue(state, now) || state.phase !== 'feedback') return false;
  if (state.index >= state.quiz.questions.length - 1) {
    finishQuiz(state, 'completed', nowIso(now));
    return true;
  }
  state.index++;
  state.items[state.index].status = 'active';
  state.phase = 'answering';
  return true;
}

function finishQuiz(state, reason, clientFinishedAt) {
  if (state.finishReason) return false;
  while (state.answers.length < state.quiz.questions.length) {
    const question = state.quiz.questions[state.answers.length];
    state.answers.push({questionId: question.id, answerCode: null, answeredAt: null});
  }
  state.answerVersion++;
  state.finishReason = reason;
  state.finishPending = true;
  state.phase = 'complete';
  state.status = 'completed';
  state.finishRecord = {
    answerVersion: state.answerVersion,
    answers: copy(state.answers),
    finishReason: reason,
    clientFinishedAt,
  };
  return true;
}

export function finishTimed(state) {
  return finishQuiz(state, 'expired', state.expiresAt);
}

export function quitQuiz(state, now = state.lastClock) {
  if (expireIfDue(state, now)) return true;
  return finishQuiz(state, 'exited', nowIso(now));
}

export function expireIfDue(state, now = state.lastClock) {
  if (!state.finishReason && now >= time(state.expiresAt)) return finishTimed(state);
  return false;
}

export function hasPending(state) {
  return state?.format === 5 && (state.progressPending || state.finishPending);
}
export const hasUploadPending = hasPending;

function sameAnswers(left, right) {
  if (left.length !== right.length) return false;
  return left.every((answer, index) => answer.questionId === right[index].questionId
    && answer.answerCode === right[index].answerCode
    && (answer.answeredAt === right[index].answeredAt
      || (answer.answeredAt !== null && right[index].answeredAt !== null
        && time(answer.answeredAt) === time(right[index].answeredAt))));
}

export function mergeServer(state, server) {
  if (server.attemptId !== state.attemptId) return false;
  const remoteVersion = Number(server.answerVersion || 0);
  const remoteAnswers = copy(server.answers || []);
  if (remoteVersion > state.answerVersion) return false;
  if (remoteVersion === state.answerVersion) {
    const localPrefix = state.answers.slice(0, remoteAnswers.length);
    if (!sameAnswers(localPrefix, remoteAnswers)) return false;
  }
  state.serverVersion = Math.max(state.serverVersion, remoteVersion);
  state.lateSync ||= Boolean(server.lateSync);

  if (server.status === 'completed') {
    state.answers = remoteAnswers;
    state.answerVersion = remoteVersion;
    state.serverVersion = remoteVersion;
    state.items = itemsFrom(state.quiz, remoteAnswers);
    state.index = Math.max(0, state.items.length - 1);
    state.status = server.status;
    state.finishReason = server.finishReason;
    state.result = server.result;
    state.progressPending = false;
    state.finishPending = false;
    state.finishRecord = null;
    state.phase = 'complete';
  } else if (!state.finishPending) {
    state.status = server.status;
    if (server.status === 'abandoned') {
      state.finishReason = server.finishReason;
      state.result = server.result;
      state.phase = 'complete';
    }
  }
  if (server.quiz?.questions) {
    const renewed = browserQuiz(server.quiz);
    state.quiz.cover = renewed.cover;
    const incoming = new Map(renewed.questions.map(question => [question.id, question]));
    for (const question of state.quiz.questions) {
      const fresh = incoming.get(question.id);
      if (!fresh) continue;
      question.media = fresh.media;
      const options = new Map(fresh.options.map(option => [option.id, option]));
      for (const option of question.options) option.media = options.get(option.id)?.media || null;
    }
  }
  return true;
}

export class PlayerClock {
  constructor(state, monotonic = () => performance.now(), wall = () => Date.now()) {
    this.state = state;
    this.monotonic = monotonic;
    this.wall = wall;
    this.base = Math.max(state.lastClock || 0, wall());
    this.mark = monotonic();
  }
  now() {
    const value = Math.max(this.base + this.monotonic() - this.mark, this.wall(), this.state.lastClock || 0);
    this.state.lastClock = value;
    return value;
  }
  iso() { return nowIso(this.now()); }
}
