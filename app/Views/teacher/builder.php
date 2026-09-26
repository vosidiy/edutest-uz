<?php $bodyClass = 'builder-page'; ?>
<?= $this->extend('layouts/teacher') ?>

<?= $this->section('head') ?>
<style>[v-cloak]{display:none!important}</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="quiz-builder" data-public-id="<?= esc($quiz['publicId'], 'attr') ?>" v-cloak>
    <div v-if="loading" class="builder-loading">Loading quiz builder…</div>
    <div v-else-if="fatalError" class="builder-fatal"><h1>Builder unavailable</h1><p>{{ fatalError }}</p><a class="btn btn-default" href="<?= site_url('quizzes') ?>">Back to quizzes</a></div>
    <template v-else>
        <header class="builder-bar">
            <div class="builder-title"><a class="btn btn-default btn-icon" href="<?= site_url('quizzes') ?>" aria-label="Back to quizzes">←</a><span><strong>{{ quiz.title || 'Untitled quiz' }}</strong><small role="status" aria-live="polite" :class="saveState">{{ saveLabel }}</small></span></div>
            <div class="builder-actions">
                <button class="btn btn-neutral" type="button" @click="preview = !preview">{{ preview ? 'Edit' : 'Preview' }}</button>
                <button class="btn btn-default" type="button" @click="save(true)" :disabled="saving">Save draft</button>
                <button v-if="quiz.status === 'draft'" class="btn btn-primary" type="button" @click="lifecycle('publish')" :disabled="saving">Publish</button>
                <button v-else-if="quiz.status === 'published'" class="btn btn-default" type="button" @click="copyShare">Copy link</button>
            </div>
        </header>

        <div v-if="quiz.frozen" class="alert alert-warning builder-banner">This quiz is frozen. Duplicate it to change questions, text, timers, answers, explanations, or media.</div>
        <div v-if="quiz.mediaLimits.serverBytes < quiz.mediaLimits.audioBytes" class="alert alert-warning builder-banner">The current PHP upload limit is {{ formatBytes(quiz.mediaLimits.serverBytes) }}. Raise <code>upload_max_filesize</code> and <code>post_max_size</code> above 20 MiB to accept the full supported audio size.</div>
        <div v-if="globalError" class="alert alert-danger builder-banner" role="alert">{{ globalError }} <button class="btn btn-link btn-sm" v-if="saveState === 'retry'" @click="save(true)">Retry</button></div>

        <div class="builder-layout">
            <aside class="question-rail" aria-label="Quiz questions">
                <div class="rail-heading"><strong>Questions</strong><span>{{ quiz.questions.length }}</span></div>
                <div class="question-list">
                    <button v-for="(question, index) in quiz.questions" :key="question.id || `new-${index}`" class="question-item" :class="{active:index === selectedIndex}" type="button" @click="selectedIndex=index">
                        <span class="question-number">{{ index + 1 }}</span><span><strong>{{ question.content || 'Untitled question' }}</strong><small>{{ typeLabel(question.type) }} · {{ question.points }} pt</small></span>
                    </button>
                </div>
                <div class="add-question" v-if="!quiz.frozen">
                    <button class="btn btn-default btn-sm" type="button" @click="addQuestion('single_choice')">＋ Single choice</button>
                    <button class="btn btn-link btn-sm" type="button" @click="addQuestion('multi_select')">Multi-select</button>
                    <button class="btn btn-link btn-sm" type="button" @click="addQuestion('short_text')">Short text</button>
                    <button class="btn btn-link btn-sm" type="button" @click="addQuestion('true_false')">True / false</button>
                </div>
            </aside>

            <main class="editor-canvas">
                <section v-if="preview" class="card preview-paper">
                    <div class="preview-heading"><span class="badge badge-primary-subtle mode-badge">{{ typeLabel(selectedQuestion?.type || '') }}</span><span>Question {{ selectedIndex + 1 }} of {{ quiz.questions.length }}</span></div>
                    <template v-if="selectedQuestion">
                        <h1>{{ selectedQuestion.content || 'Untitled question' }}</h1>
                        <media-preview :media="selectedQuestion.media"></media-preview>
                        <div v-if="selectedQuestion.type === 'short_text'" class="preview-text-answer">Student types an answer here</div>
                        <div v-else class="preview-options"><div v-for="(option,index) in selectedQuestion.options" :key="option.id || index"><span>{{ String.fromCharCode(65+index) }}</span><strong>{{ option.content || 'Untitled answer' }}</strong><media-preview :media="option.media"></media-preview></div></div>
                    </template>
                    <div v-else class="empty-state"><h2>Add a question to preview the quiz</h2></div>
                </section>

                <section v-else-if="selectedQuestion" class="card editor-paper">
                    <div class="editor-paper-head"><span>Question {{ selectedIndex + 1 }} of {{ quiz.questions.length }}</span><div><button class="btn btn-default btn-sm btn-icon" type="button" @click="moveQuestion(-1)" :disabled="quiz.frozen || selectedIndex===0" aria-label="Move question up">↑</button><button class="btn btn-default btn-sm btn-icon" type="button" @click="moveQuestion(1)" :disabled="quiz.frozen || selectedIndex===quiz.questions.length-1" aria-label="Move question down">↓</button><button class="btn btn-red-subtle btn-sm" type="button" @click="removeQuestion" :disabled="quiz.frozen">Delete</button></div></div>
                    <div class="question-editor">
                        <div class="editor-grid">
                            <label class="form-field"><span class="form-label">Question</span><textarea class="form-control" v-model="selectedQuestion.content" :disabled="quiz.frozen" rows="4" maxlength="65535"></textarea><small class="form-error" v-if="fieldError(`questions.${selectedIndex}.content`)">{{ fieldError(`questions.${selectedIndex}.content`) }}</small></label>
                            <label class="form-field"><span class="form-label">Answer type</span><select class="form-control" :value="selectedQuestion.type" @change="changeQuestionType($event.target.value)" :disabled="quiz.frozen"><option value="single_choice">Single choice</option><option value="multi_select">Multi-select</option><option value="short_text">Short text</option></select></label>
                        </div>
                        <div class="question-meta-fields"><label class="form-field compact"><span class="form-label">Points</span><input class="form-control" v-model="selectedQuestion.points" type="number" min="0.01" max="10000" step="0.01" :disabled="quiz.frozen"></label><label class="form-field compact"><span class="form-label">Question timer (seconds)</span><input class="form-control" v-model="selectedQuestion.timeLimitSec" type="number" min="1" max="86400" placeholder="No timer" :disabled="quiz.frozen"></label></div>

                        <div class="media-editor"><div><strong>Question media</strong><small>One image, audio file, or supported video.</small></div><media-preview :media="selectedQuestion.media"></media-preview><div class="media-actions" v-if="!quiz.frozen"><button class="btn btn-default btn-sm" type="button" @click="chooseFile(selectedIndex,null)">Upload file</button><button class="btn btn-default btn-sm" type="button" @click="addVideo(selectedIndex,null)">Video URL</button><button v-if="selectedQuestion.media" class="btn btn-red-subtle btn-sm" type="button" @click="removeMedia(selectedIndex,null)">Remove</button></div></div>

                        <div v-if="selectedQuestion.type === 'short_text'" class="answer-editor">
                            <div class="section-label"><strong>Accepted answers</strong><small>Matching ignores capitalization and repeated spaces.</small></div>
                            <div v-for="(answer,index) in selectedQuestion.textAnswers" :key="index" class="text-answer-row"><input class="form-control" v-model="selectedQuestion.textAnswers[index]" maxlength="500" :disabled="quiz.frozen"><button class="btn btn-red-subtle btn-sm btn-icon" type="button" @click="selectedQuestion.textAnswers.splice(index,1)" :disabled="quiz.frozen" aria-label="Remove accepted answer">×</button></div>
                            <button class="btn btn-default btn-sm" type="button" @click="selectedQuestion.textAnswers.push('')" :disabled="quiz.frozen">＋ Add accepted answer</button>
                        </div>
                        <div v-else class="answer-editor">
                            <div class="section-label"><strong>Answer choices</strong><small>{{ selectedQuestion.type === 'multi_select' ? 'Select every correct choice.' : 'Select exactly one correct choice.' }}</small></div>
                            <div v-for="(option,index) in selectedQuestion.options" :key="option.id || `option-${index}`" class="answer-row">
                                <button class="btn btn-default btn-sm btn-icon correct-toggle" :class="{selected:option.isCorrect}" type="button" @click="toggleCorrect(index)" :disabled="quiz.frozen" :aria-label="`Mark answer ${index+1} correct`">✓</button>
                                <div><input class="form-control" v-model="option.content" maxlength="65535" :disabled="quiz.frozen" :aria-label="`Answer ${index+1}`"><media-preview :media="option.media"></media-preview><div class="media-actions small" v-if="!quiz.frozen"><button class="btn btn-link btn-sm" type="button" @click="chooseFile(selectedIndex,index)">Media</button><button class="btn btn-link btn-sm" type="button" @click="addVideo(selectedIndex,index)">Video</button><button v-if="option.media" class="btn btn-link btn-sm danger" type="button" @click="removeMedia(selectedIndex,index)">Remove</button></div></div>
                                <button class="btn btn-red-subtle btn-sm btn-icon delete-button" type="button" @click="removeOption(index)" :disabled="quiz.frozen" :aria-label="`Delete answer ${index+1}`">×</button>
                            </div>
                            <button class="btn btn-default btn-sm" type="button" @click="addOption" :disabled="quiz.frozen">＋ Add answer</button>
                        </div>
                        <label class="form-field explanation"><span class="form-label">Explanation shown with feedback</span><textarea class="form-control" v-model="selectedQuestion.explanation" :disabled="quiz.frozen" rows="3" maxlength="65535"></textarea></label>
                    </div>
                </section>
                <section v-else class="card editor-paper empty-state"><span>＋</span><h2>Start with a question</h2><p>Add a question from the left rail.</p></section>
            </main>

            <aside class="settings-rail" aria-label="Quiz settings">
                <div class="rail-heading"><strong>Quiz settings</strong><span class="badge status" :class="quiz.status">{{ quiz.status }}</span></div>
                <div class="settings-content">
                    <details><summary><?= esc(lang('Player.ui.cover')) ?></summary><div class="setting-group">
                        <p class="muted"><?= esc(lang('Player.ui.coverHelp')) ?></p>
                        <media-preview :media="quiz.cover"></media-preview>
                        <div class="media-actions" v-if="!quiz.frozen">
                            <button class="btn btn-default btn-sm" type="button" :disabled="coverUploading" @click="chooseFile(-1,null)"><?= esc(lang('Player.ui.uploadCover')) ?></button>
                            <button v-if="quiz.cover" class="btn btn-red-subtle btn-sm" type="button" :disabled="coverUploading" @click="removeMedia(-1,null)"><?= esc(lang('Player.ui.removeCover')) ?></button>
                        </div>
                        <div v-if="coverUploading" role="status"><progress aria-label="<?= esc(lang('Player.ui.coverUploading'), 'attr') ?>"></progress> <?= esc(lang('Player.ui.coverUploading')) ?></div>
                    </div></details>
                    <p class="alert alert-warning"><?= esc(lang('Player.ui.teacherDisclosure')) ?></p>
                    <details open><summary>Details</summary><div class="setting-group"><label class="form-field"><span class="form-label">Title</span><input class="form-control" v-model="quiz.title" maxlength="200" :disabled="quiz.frozen"></label><label class="form-field"><span class="form-label">Description</span><textarea class="form-control" v-model="quiz.description" rows="3" :disabled="quiz.frozen"></textarea></label><label class="form-field"><span class="form-label">Instructions</span><textarea class="form-control" v-model="quiz.instructions" rows="3" :disabled="quiz.frozen"></textarea></label><label class="form-field"><span class="form-label">Mode</span><select class="form-control" :value="quiz.mode" @change="changeMode($event.target.value)" :disabled="quiz.frozen"><option value="assessment">Assessment</option><option value="practice">Practice</option></select></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.listed"><span><strong>List on public profile</strong><small>Takes effect when public profiles launch.</small></span></label></div></details>
                    <details><summary>Schedule & timing</summary><div class="setting-group"><label class="form-field"><span class="form-label">Opens ({{ quiz.timezone }})</span><input class="form-control" type="datetime-local" v-model="quiz.opensAtLocal"></label><label class="form-field"><span class="form-label">Closes ({{ quiz.timezone }})</span><input class="form-control" type="datetime-local" v-model="quiz.closesAtLocal"></label><label class="form-field"><span class="form-label">Total timer (seconds)</span><input class="form-control" type="number" min="1" max="86400" v-model="quiz.timeLimitSec" :disabled="quiz.frozen" placeholder="No timer"></label></div></details>
                    <details v-if="quiz.mode === 'assessment'"><summary>Admission</summary><div class="setting-group"><label class="form-field"><span class="form-label">New passcode</span><input class="form-control" type="password" v-model="passcodeValue" @input="quiz.passcode.action='set'" autocomplete="new-password" placeholder="Leave unchanged"></label><button v-if="quiz.passcode.configured" class="btn btn-link btn-sm align-start" type="button" @click="clearPasscode">Clear current passcode</button><label class="form-field"><span class="form-label">Email</span><select class="form-control" v-model="quiz.emailMode"><option value="hidden">Hidden</option><option value="optional">Optional</option><option value="required">Required</option></select></label><label class="form-field"><span class="form-label">Phone</span><select class="form-control" v-model="quiz.phoneMode"><option value="hidden">Hidden</option><option value="optional">Optional</option><option value="required">Required</option></select></label></div></details>
                    <details><summary>Behavior</summary><div class="setting-group"><label class="form-check check-row"><input type="checkbox" v-model="quiz.shuffleQuestions"><span><strong>Shuffle questions</strong><small>Randomize order for each start.</small></span></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.shuffleOptions"><span><strong>Shuffle choices</strong><small>Randomize choice order.</small></span></label><label v-if="quiz.mode === 'assessment'" class="form-check check-row"><input type="checkbox" v-model="quiz.cheatCheck"><span><strong>Integrity monitoring</strong><small>Record warnings without penalties.</small></span></label></div></details>
                    <details><summary>Feedback & results</summary><div class="setting-group"><label class="form-field"><span class="form-label">Feedback timing</span><select class="form-control" v-model="quiz.feedback"><option value="at_end">At the end</option><option value="after_each">After each question</option></select></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.showScore"><span><strong>Show score</strong></span></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.showAnswers"><span><strong>Show correct answers</strong></span></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.showExplain" :disabled="!quiz.showAnswers"><span><strong>Show explanations</strong></span></label></div></details>
                    <details open v-if="quiz.status !== 'draft'"><summary>Sharing</summary><div class="setting-group"><label class="form-field"><span class="form-label">Stable share link</span><input class="form-control" :value="quiz.shareUrl" readonly></label><button class="btn btn-default" type="button" @click="copyShare">Copy link</button></div></details>
                </div>
            </aside>
        </div>

        <dialog class="dialog app-dialog" ref="conflictDialog"><div class="dialog-heading"><h2>Newer changes found</h2></div><p class="dialog-copy">This quiz changed in another tab. Reload its latest version, or overwrite it with this tab’s draft.</p><div class="dialog-actions"><button class="btn btn-default" @click="reloadConflict">Reload latest</button><button class="btn btn-primary" @click="overwriteConflict">Overwrite</button></div></dialog>
    </template>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="/assets/vendor/vue/vue.global.prod.js"></script>
<script src="/assets/js/builder.js"></script>
<?= $this->endSection() ?>
