import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/js/builder.js', import.meta.url), 'utf8');
const window = {};
vm.runInNewContext(source, {window, document: {querySelector: () => null}});

const rules = window.EduTestBuilderRules;
assert.ok(rules, 'Builder rules should be available before the Vue application mounts.');
assert.equal(rules.shouldAutosave({status: 'draft'}), true);
assert.equal(rules.shouldAutosave({status: 'published'}), true);
assert.equal(rules.shouldAutosave({status: 'closed'}), true);
assert.equal(rules.shouldAutosave({status: 'archived'}), false);

assert.deepEqual(
  {...rules.modeTransition('practice', 'assessment', () => true)},
  {accepted: true, mode: 'assessment', clearAssessmentSettings: false}
);
assert.deepEqual(
  {...rules.modeTransition('assessment', 'practice', () => true)},
  {accepted: true, mode: 'practice', clearAssessmentSettings: true}
);
assert.deepEqual(
  {...rules.modeTransition('practice', 'assessment', () => false)},
  {accepted: false, mode: 'practice', clearAssessmentSettings: false}
);

const complete = {
  status: 'draft', title: 'Complete quiz', questions: [{
    type: 'single_choice', content: 'Question?',
    options: [{content: 'A', isCorrect: true, media: null}, {content: 'B', isCorrect: false, media: null}]
  }]
};
assert.deepEqual([...rules.publicationIssues(complete)], []);
assert.ok(rules.publicationIssues({...complete, title: ''}).length > 0);
assert.ok(rules.publicationIssues({...complete, questions: []}).length > 0);
assert.ok(rules.publicationIssues({...complete, questions: [{...complete.questions[0], content: ''}]}).length > 0);
assert.deepEqual([...rules.publicationIssues({...complete, questions: [{type: 'short_text', content: 'Spell it', textAnswers: ['answer']} ]})], []);
assert.ok(rules.publicationIssues({...complete, questions: [{type: 'short_text', content: 'Spell it', textAnswers: [' ']}]}).length > 0);

console.log('Builder autosave and publication-readiness tests passed.');
