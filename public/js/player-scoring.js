(function (global) {
  'use strict';

  const player = global.EduTestPlayer = global.EduTestPlayer || {};

  // Local grading powers immediate feedback; the server computes the official result at Finish.
  // Answer keys are deliberately client-visible. This is feedback, not an anti-cheat boundary.
  function grade(question, input = {}) {
    const code = input.answerCode ?? input.answerCodes?.[0] ?? null;
    if (code !== null && !question.options.some(option => option.code === code)) throw new Error('Invalid answer');
    if (code === null) return {result: 'skipped', isCorrect: false};
    const isCorrect = code === question.correctCode;
    return {result: isCorrect ? 'correct' : 'wrong', isCorrect};
  }

  function summarize(questions, answers) {
    const items = questions.map((question, index) => ({questionId: question.id, ...grade(question, answers[index] || {})}));
    const score = items.filter(item => item.result === 'correct').length;
    const maximum = questions.length;
    return {official: false, score, maxScore: maximum, percent: (score * 100 / maximum).toFixed(2), items};
  }

  Object.assign(player, {grade, summarize});
})(globalThis);
