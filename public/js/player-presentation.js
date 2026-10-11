export function completionEmoji(percent) {
  const value = Number(percent);
  if (!Number.isFinite(value)) return '🎉';
  if (value >= 90) return '🏆';
  if (value >= 80) return '🤩';
  if (value >= 70) return '👍';
  if (value >= 60) return '😊';
  if (value >= 50) return '🥺';
  return '😢';
}

export function resultCounts(items, total) {
  const counts = {correct: 0, wrong: 0, unanswered: 0};
  for (const item of Array.isArray(items) ? items : []) {
    if (item?.result === 'correct') counts.correct++;
    else if (item?.result === 'wrong') counts.wrong++;
    else counts.unanswered++;
  }
  const maximum = Number(total);
  if (Number.isFinite(maximum)) {
    const missing = Math.max(0, Math.trunc(maximum) - counts.correct - counts.wrong - counts.unanswered);
    counts.unanswered += missing;
  }
  return counts;
}
