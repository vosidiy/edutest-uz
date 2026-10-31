import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/js/builder.js', import.meta.url), 'utf8');
const header = fs.readFileSync(new URL('../../app/Views/teacher/partials/quiz_header.php', import.meta.url), 'utf8');
assert.doesNotMatch(source, /autosave|scheduleAutosave|shouldAutosave/i);
assert.doesNotMatch(source, /addVideo|embedUrl|directVideo|<video|<iframe/i);
assert.doesNotMatch(source + header, /preview\s*:|this\.preview|preview\s*=\s*(?:true|false)|preview-paper|preview-options/i);
assert.doesNotMatch(source + header, /version_conflict|conflictDialog|overwriteConflict|quiz\.listed|Show on my page/i);
assert.match(header, /:class="hasChanges \? 'btn-primary' : 'btn-default'"/);
assert.match(header, /hasChanges \? 'Save quiz' : 'Saved'/);
const window = {};
vm.runInNewContext(source, {window, document: {querySelector: () => null}});

const rules = window.EduTestBuilderRules;
assert.ok(rules, 'Builder rules should be available before the Vue application mounts.');
for (const [input, expected] of [[1, 1], [1.4, 1], [1.5, 2], ['60.4', 60], [120.4, 120], [0.5, 1],
  [0, 120], [-1, 120], [120.5, 120], [121, 120], ['', 120], ['letters', 120], ['0x10', 120], [null, 120], [[], 120], [{}, 120], [Infinity, 120]]) {
  assert.equal(rules.normalizeTimerMinutes(input), expected);
}
for (const [input, pool, expected] of [[1, 5, 1], [2.4, 5, 2], [2.5, 5, 3], ['4.4', 5, 4], [0.5, 5, 1], [5, 5, 5],
  [0, 5, null], [-1, 5, null], [5.5, 5, null], [6, 5, null], ['', 5, null], ['letters', 5, null], ['0x2', 5, null],
  [null, 5, null], [[], 5, null], [{}, 5, null], [Infinity, 5, null], [1, 0, null]]) {
  assert.equal(rules.normalizeDrawCount(input, pool), expected);
}

const quiz = {
  status: 'published', title: 'Complete quiz', timeLimitMinutes:120, questions: [{
    content: '', optionsLayout: 'list',
    options: [{content: '', isCorrect: false, media: null}]
  }]
};
assert.equal(rules.repairQuestions(quiz), true);
assert.deepEqual(quiz.questions[0].options.map(option => option.content), ['Option 1', 'Option 2']);
assert.deepEqual(quiz.questions[0].options.map(option => option.isCorrect), [true, false]);
assert.equal(rules.repairQuestions(quiz), false);

const active = JSON.parse(rules.editable(quiz));
assert.equal(active.status, 'published');
assert.equal(active.timeLimitMinutes, 120);
assert.equal('timeLimitSec' in active, false);

console.log('Builder manual-save normalization rules passed.');
