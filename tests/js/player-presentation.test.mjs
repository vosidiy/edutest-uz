import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {completionEmoji, resultCounts, shouldRevealCorrectAnswer, wholePercent} from '../../public/js/player-presentation.js';

const playerSource = fs.readFileSync(new URL('../../public/js/player.js', import.meta.url), 'utf8');
const viewSource = fs.readFileSync(new URL('../../public/js/player-view.js', import.meta.url), 'utf8');
const templates = fs.readFileSync(new URL('../../app/Views/student/partials/player_templates.php', import.meta.url), 'utf8');
const layout = fs.readFileSync(new URL('../../app/Views/layouts/student.php', import.meta.url), 'utf8');

test('player media supports image and audio without video or embed renderers', () => {
  assert.match(playerSource, /\['image','audio'\]/);
  assert.doesNotMatch(playerSource + viewSource + templates, /embedUrl|player-video-template|player-embed-template|mediaVideo/);
  assert.doesNotMatch(templates, /<video|<iframe/i);
});

test('completion emoji follows every score boundary, including decimals', () => {
  const cases = [
    [100, '🏆'], [90, '🏆'], [89.99, '🤩'], [80, '🤩'],
    [79.99, '👍'], [70, '👍'], [69.99, '😊'], [60, '😊'],
    [59.99, '🥺'], [50, '🥺'], [49.99, '😢'], [0, '😢'],
  ];
  for (const [percent, expected] of cases) assert.equal(completionEmoji(percent), expected, String(percent));
  assert.equal(completionEmoji(undefined), '🎉');
});

test('results display truncated whole percentages without changing precise presentation rules', () => {
  assert.equal(wholePercent('12.50'), 12);
  assert.equal(wholePercent('99.99'), 99);
  assert.equal(wholePercent(100), 100);
  assert.equal(wholePercent(undefined), 0);
});

test('correct-answer feedback respects verdict and teacher visibility', () => {
  assert.equal(shouldRevealCorrectAnswer('correct', true), false);
  assert.equal(shouldRevealCorrectAnswer('wrong', true), true);
  assert.equal(shouldRevealCorrectAnswer('skipped', true), true);
  assert.equal(shouldRevealCorrectAnswer('wrong', false), false);
});

test('question progress is in the player body without duplicate title or identity hooks', () => {
  assert.match(templates, /data-slot="progress-label"/);
  assert.match(templates, /aria-labelledby="player-progress-label"/);
  assert.doesNotMatch(templates, /player-quiz-title|data-slot="identity"[\s\S]*player-timers/);
  assert.doesNotMatch(layout, /player-header-progress|player-header-separator/);
  assert.doesNotMatch(viewSource, /yourAnswer|data-slot[^\n]*answer/);
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
