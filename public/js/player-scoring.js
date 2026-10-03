// Answer keys are deliberately client-visible. This is feedback, not an anti-cheat boundary.
export function normalize(value) { return value.replace(/\p{White_Space}+/gu, ' ').replace(/^ +| +$/g, '').toLowerCase(); }
export function grade(question, input = {}) {
  const codes = input.answerCodes || [];
  const text = input.textAnswer || '';
  if (!Array.isArray(codes) || new Set(codes).size !== codes.length || typeof text !== 'string' || [...text].length > 500) throw new Error('Invalid answer');
  if (question.type === 'short_text') {
    if (codes.length) throw new Error('Invalid answer');
    const value = normalize(text);
    const correct = value !== '' && question.acceptedAnswers.some(answer => normalize(answer) === value);
    return {result: !value ? 'unanswered' : correct ? 'correct' : 'wrong', isCorrect: correct};
  }
  if (text || codes.some(code => !question.options.some(option => option.code === code)) || (question.type === 'single_choice' && codes.length > 1)) throw new Error('Invalid answer');
  if (!codes.length) return {result: 'unanswered', isCorrect: false};
  const selected = [...codes].sort();
  const correct = [...new Set(question.correctCodes)].sort();
  if (!correct.length) throw new Error('Invalid question');
  const isCorrect = JSON.stringify(selected) === JSON.stringify(correct);
  return {result: isCorrect ? 'correct' : 'wrong', isCorrect};
}
export function summarize(questions, items) {
  const results = questions.map((question, index) => ({questionId: question.id,
    ...grade(question, items[index]?.answerStatus === 'answered' ? items[index] : {})}));
  const score = results.filter(item => item.result === 'correct').length;
  const maximum = questions.length;
  return {confirmed: false, score:String(score), maxScore:String(maximum), percent:(score * 100 / maximum).toFixed(2), items:results};
}
