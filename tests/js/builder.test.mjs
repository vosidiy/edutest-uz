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
assert.equal(rules.shouldAutosave({status: 'archived'}), false);

const complete = {
  status: 'draft', title: 'Complete quiz', questions: [{
    content: 'Question?', optionsLayout: 'list',
    options: [{content: 'A', isCorrect: true, media: null}, {content: 'B', isCorrect: false, media: null}]
  }]
};
assert.deepEqual([...rules.publicationIssues(complete)], []);
assert.ok(rules.publicationIssues({...complete, title: ''}).length > 0);
assert.ok(rules.publicationIssues({...complete, questions: []}).length > 0);
assert.ok(rules.publicationIssues({...complete, questions: [{...complete.questions[0], content: ''}]}).length > 0);
assert.ok(rules.publicationIssues({...complete, drawCount: 2}).length > 0);
assert.ok(rules.publicationIssues({...complete, questions: [{...complete.questions[0], options: [{content:'A', isCorrect:true}]}]}).length > 0);
assert.ok(rules.publicationIssues({...complete, questions: [{...complete.questions[0], options: [{content:'A', isCorrect:true}, {content:'B', isCorrect:true}]}]}).length > 0);

console.log('Builder autosave and publication-readiness tests passed.');
