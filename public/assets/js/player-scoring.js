// Answer keys are deliberately client-visible. This is feedback, not an anti-cheat boundary.
export function cents(value) {
  const match = /^(\d{1,10})(?:\.(\d{1,2}))?$/.exec(String(value));
  if (!match) throw new Error('Invalid decimal');
  return BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0'));
}
export function decimal(value) { return `${value / 100n}.${String(value % 100n).padStart(2, '0')}`; }
export function roundedRatio(numerator, denominator) {
  return numerator / denominator + (numerator % denominator * 2n >= denominator ? 1n : 0n);
}
export function normalize(value) { return value.replace(/\p{White_Space}+/gu, ' ').replace(/^ +| +$/g, '').toLowerCase(); }
export function grade(question, input = {}) {
  const codes = input.answerCodes || [];
  const text = input.textAnswer || '';
  const maximum = cents(question.points);
  if (!Array.isArray(codes) || new Set(codes).size !== codes.length || typeof text !== 'string' || [...text].length > 500) throw new Error('Invalid answer');
  if (question.type === 'short_text') {
    if (codes.length) throw new Error('Invalid answer');
    const value = normalize(text);
    const correct = value !== '' && question.acceptedAnswers.some(answer => normalize(answer) === value);
    return {result: !value ? 'unanswered' : correct ? 'correct' : 'wrong', points: decimal(correct ? maximum : 0n)};
  }
  if (text || codes.some(code => !question.options.some(option => option.code === code)) || (question.type === 'single_choice' && codes.length > 1)) throw new Error('Invalid answer');
  if (!codes.length) return {result: 'unanswered', points: '0.00'};
  const right = codes.filter(code => question.correctCodes.includes(code)).length;
  const totalRight = question.correctCodes.length;
  if (!totalRight) throw new Error('Invalid question');
  if (question.type === 'single_choice') return {result: right === 1 ? 'correct' : 'wrong', points: decimal(right === 1 ? maximum : 0n)};
  const wrong = codes.length - right;
  const totalWrong = question.options.length - totalRight;
  const numerator = Math.max(0, right * Math.max(1, totalWrong) - wrong * totalRight);
  const denominator = totalRight * Math.max(1, totalWrong);
  const award = roundedRatio(maximum * BigInt(numerator), BigInt(denominator));
  return {result: right === totalRight && !wrong ? 'correct' : award > 0n ? 'partial' : 'wrong', points: decimal(award)};
}
export function summarize(questions, items) {
  const results = questions.map((question, index) => ({questionId: question.id, ...grade(question, items[index])}));
  const score = results.reduce((sum, item) => sum + cents(item.points), 0n);
  const maximum = questions.reduce((sum, question) => sum + cents(question.points), 0n);
  return {confirmed: false, score: decimal(score), maxScore: decimal(maximum), percent: decimal(roundedRatio(score * 10000n, maximum)), items: results};
}
