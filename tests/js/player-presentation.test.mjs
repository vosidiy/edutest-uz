import test from 'node:test';
import assert from 'node:assert/strict';
import {completionEmoji, resultCounts} from '../../public/js/player-presentation.js';

test('completion emoji follows every score boundary, including decimals', () => {
  const cases = [
    [100, '🏆'], [90, '🏆'], [89.99, '🤩'], [80, '🤩'],
    [79.99, '👍'], [70, '👍'], [69.99, '😊'], [60, '😊'],
    [59.99, '🥺'], [50, '🥺'], [49.99, '😢'], [0, '😢'],
  ];
  for (const [percent, expected] of cases) assert.equal(completionEmoji(percent), expected, String(percent));
  assert.equal(completionEmoji(undefined), '🎉');
});

test('result counts keep wrong and unanswered questions separate', () => {
  assert.deepEqual(resultCounts([
    {result: 'correct'},
    {result: 'wrong'},
    {result: 'skipped'}, // Explicit skip.
    {result: 'skipped'}, // Unreached after exit or timeout.
  ], 4), {correct: 1, wrong: 1, unanswered: 2});
});

test('missing result rows are conservatively counted as unanswered', () => {
  assert.deepEqual(resultCounts([{result: 'correct'}], 3), {correct: 1, wrong: 0, unanswered: 2});
});
