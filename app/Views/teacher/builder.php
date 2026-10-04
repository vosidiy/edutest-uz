<?= $this->extend('layouts/teacher') ?>

<?= $this->section('head') ?>
<style>[v-cloak]{display:none!important}</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="quiz-builder" data-workspace-messages="<?= esc(json_encode(lang('EduTest.builder.workspace'), JSON_THROW_ON_ERROR), 'attr') ?>" data-public-id="<?= esc($quiz['publicId'], 'attr') ?>" data-publication-messages="<?= esc(json_encode(lang('EduTest.builder.publication'), JSON_THROW_ON_ERROR), 'attr') ?>" data-mode-messages="<?= esc(json_encode(lang('EduTest.builder.modeChange'), JSON_THROW_ON_ERROR), 'attr') ?>" v-cloak>
    <div v-if="loading" class="builder-loading">Loading quiz builder…</div>
    <div v-else-if="fatalError" class="builder-fatal"><h1>Builder unavailable</h1><p>{{ fatalError }}</p><a class="btn btn-default" href="<?= site_url('dashboard') ?>"><?= esc(lang('Workspace.backDashboard')) ?></a></div>
    <template v-else>
        <?= $this->include('teacher/partials/quiz_header') ?>

        <nav class="builder-panel-switcher" aria-label="<?= esc(lang('EduTest.builder.workspace.panels'), 'attr') ?>">
            <?php foreach (['questions', 'editor', 'settings'] as $panel) : ?>
                <button class="btn btn-default" type="button" :aria-pressed="activePanel === '<?= $panel ?>'" aria-controls="builder-<?= $panel ?>" @click="showPanel('<?= $panel ?>')"><?= esc(lang('EduTest.builder.workspace.' . $panel)) ?></button>
            <?php endforeach ?>
        </nav>

        <div class="builder-layout" :data-active-panel="activePanel">
            <aside id="builder-questions" class="question-rail" aria-label="Quiz questions">
                <div class="rail-heading">
                    <strong class="rail-title">Questions ({{ quiz.questions.length }})</strong>
                </div>
                
                <div class="question-list" ref="questionsPanel" tabindex="0" role="region" aria-label="<?= esc(lang('EduTest.builder.workspace.questions'), 'attr') ?>">
                    <button v-for="(question, index) in quiz.questions" :key="question.id || `new-${index}`" class="question-item" :class="{active:index === selectedIndex}" type="button" @click="selectQuestion(index)">
                        <span class="question-number">{{ index + 1 }}</span><span><strong>{{ question.content || 'Untitled question' }}</strong><small>{{ typeLabel(question.type) }}</small></span>
                    </button>
                </div>

                <div class="add-question">
                    <button class="btn btn-default" type="button" @click="addQuestion">＋ Add question</button>
                </div>
            </aside>

            <section id="builder-editor" class="editor-canvas" ref="editorPanel" tabindex="0" aria-label="<?= esc(lang('EduTest.builder.workspace.editor'), 'attr') ?>">
                <div class="builder-notices">
                    <p v-if="(quiz.status === 'draft' || quiz.hasUnpublishedChanges) && !publishReady" id="publish-readiness" class="builder-publish-readiness" role="status">{{ publishReadinessMessage }}</p>
                    <div v-if="quiz.hasPublished" class="alert alert-primary builder-banner"><?= esc(lang('EduTest.builder.publishedWorkingCopyNotice')) ?></div>
                    <div v-if="quiz.mediaLimits.serverBytes < quiz.mediaLimits.audioBytes" class="alert alert-warning builder-banner">The current PHP upload limit is {{ formatBytes(quiz.mediaLimits.serverBytes) }}. Raise <code>upload_max_filesize</code> and <code>post_max_size</code> above 20 MiB to accept the full supported audio size.</div>
                    <div v-if="globalError" class="alert alert-danger builder-banner" role="alert">{{ globalError }} <button class="btn btn-link btn-sm" v-if="saveState === 'retry'" @click="save(true)">Retry</button></div>
                </div>
                <section v-if="preview" class="card preview-paper">
                    <div class="preview-heading"><span class="badge badge-primary-subtle mode-badge">{{ typeLabel(selectedQuestion?.type || '') }}</span><span>Question {{ selectedIndex + 1 }} of {{ quiz.questions.length }}</span></div>
                    <template v-if="selectedQuestion">
                        <h1>{{ selectedQuestion.content || 'Untitled question' }}</h1>
                        <media-preview :media="selectedQuestion.media"></media-preview>
                        <div v-if="selectedQuestion.type === 'short_text'" class="preview-text-answer">Student types an answer here</div>
                        <div v-else class="preview-options"><div v-for="(option,index) in selectedQuestion.options" :key="option.id || index"><span>{{ optionLetter(index) }})</span><strong>{{ option.content || 'Untitled answer' }}</strong><media-preview :media="option.media"></media-preview></div></div>
                    </template>
                    <div v-else class="empty-state"><h2>Add a question to preview the quiz</h2></div>
                </section>

                <section v-else-if="selectedQuestion" class="card editor-paper">
                    <div class="editor-paper-head">
                        
                        <span class="text-secondary text-uppercase text-sm">Question {{ selectedIndex + 1 }} of {{ quiz.questions.length }}</span>
                        
                        <div>
                            <button class="btn btn-default btn-sm btn-icon" type="button" @click="moveQuestion(-1)" :disabled="selectedIndex===0" aria-label="Move question up">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-up preview-icon"><path d="M9 19a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-6a1 1 0 0 1 1-1h3.293a.707.707 0 0 0 .5-1.207l-7.086-7.086a1 1 0 0 0-1.414 0l-7.086 7.086a.707.707 0 0 0 .5 1.207H8a1 1 0 0 1 1 1z"/></svg>
                            </button>
                            
                            <button class="btn btn-default btn-sm btn-icon" type="button" @click="moveQuestion(1)" :disabled="selectedIndex===quiz.questions.length-1" aria-label="Move question down">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-down preview-icon"><path d="M9 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 0 1 1h3.293a.707.707 0 0 1 .5 1.207l-7.086 7.086a1 1 0 0 1-1.414 0l-7.086-7.086a.707.707 0 0 1 .5-1.207H8a1 1 0 0 0 1-1z"/></svg>
                            </button>
                            
                            <button class="btn btn-red-subtle btn-sm" type="button" @click="removeQuestion">Delete</button>
                        </div>
                    
                    </div>
                    <div class="question-editor">

                        <div class="mb-4" style="max-width:300px">
                            <label class="form-field">
                                <select class="form-select" :value="selectedQuestion.type" @change="changeQuestionType($event.target.value)"><option value="single_choice">Single choice</option><option value="multi_select">Multi-select</option><option value="short_text">Short text</option>
                            </select>
                        </label>
                        </div>
                        
                        <div class="editor">
                            
                            <label class="form-field">
                                <h5 class="mb-2">Question</h5>
                                
                                <textarea placeholder="Type question text..." class="form-control" v-model="selectedQuestion.content" rows="2" maxlength="65535"></textarea>
                                
                                <small class="form-error" v-if="fieldError(`questions.${selectedIndex}.content`)">{{ fieldError(`questions.${selectedIndex}.content`) }}</small>
                            </label>
 
                        </div>

                        <div class="media-editor">       
                            <media-preview :media="selectedQuestion.media"></media-preview>
                            <div class="d-flex">
                                <div class="media-actions">
                                    <button class="btn btn-default" type="button" @click="chooseFile(selectedIndex,null)"> <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image preview-icon"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg> <span>Add image</span></button><button class="btn btn-default" type="button" @click="addVideo(selectedIndex,null)">Video URL</button><button v-if="selectedQuestion.media" class="btn btn-red-subtle" type="button" @click="removeMedia(selectedIndex,null)">Remove</button>
                                </div>
                                <div class="ml-3">
                                    <p>Question media</p>
                                    <small class="text-secondary">One image, audio file, or supported video.</small>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedQuestion.type === 'short_text'" class="answer-editor">
                            <div class="section-label">
                                <h5>Accepted answers</h5>
                            </div>
                            <div v-for="(answer,index) in selectedQuestion.textAnswers" :key="index" class="text-answer-row">
                                <input class="form-control" v-model="selectedQuestion.textAnswers[index]" maxlength="500">
                                <button class="btn btn-plain btn-icon" type="button" @click="selectedQuestion.textAnswers.splice(index,1)" aria-label="Remove accepted answer">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x preview-icon"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                </button>
                            </div>
                            <button style="max-width:464px" class="btn w-full btn-neutral" type="button" @click="selectedQuestion.textAnswers.push('')">＋ Add correct answer</button>
                        </div>
                        <div v-else class="answer-editor mb-5">

                            <div class="section-label">
                                <h5>Answer choices</h5>
                                <small>{{ selectedQuestion.type === 'multi_select' ? 'Select every correct choice.' : 'Select exactly one correct choice.' }}</small>
                            </div>

                            <div v-for="(option,index) in selectedQuestion.options" :key="option.id || `option-${index}`" class="answer-row">
                                <div class="answer-main-row">
                                    <span class="answer-letter">{{ optionLetter(index) }})</span>
                                    <input class="form-control" v-model="option.content" maxlength="65535" :aria-label="`Answer ${optionLetter(index)}`">
                                    
                                    <button class="btn btn-default" type="button" @click="chooseFile(selectedIndex,index)">
                                        <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image preview-icon"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                        <span>
                                             {{ option.media ? 'Change' : 'Image' }}
                                        </span>
                                    </button>
                                    
                                    <button v-if="option.media" class="btn btn-link" type="button" @click="removeMedia(selectedIndex,index)">Remove image</button>
                                    
                                    <button class="btn btn-red-subtle btn-icon" type="button" @click="removeOption(index)" :aria-label="`Remove answer ${optionLetter(index)}`">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x preview-icon"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                    </button>
                                </div>
                                <media-preview :media="option.media"></media-preview>
                                <label class="form-check answer-correct-row"><input :type="selectedQuestion.type === 'single_choice' ? 'radio' : 'checkbox'" :name="`correct-answer-${selectedIndex}`" :checked="option.isCorrect" @change="setCorrect(index,$event.target.checked)"><span>Mark as correct</span></label>
                            </div>
                            <button class="btn btn-neutral w-full" type="button" @click="addOption">
                                ＋ Add answer variant
                            </button>
                        </div>
                        <hr>
                        <label class="form-field explanation">
                            <h5 class="mb-2">Explanation or feedback <span class="text-secondary">(optional)</span></h5>
                            <textarea placeholder="Type why certain answer is correct" class="form-control" v-model="selectedQuestion.explanation" rows="2" maxlength="900"></textarea>
                        </label>
                    </div>
                </section>
                <section v-else class="card editor-paper empty-state"><span>＋</span><h2>Start with a question</h2><p>Add a question from the left rail.</p></section>
                <details class="builder-disclosure"><summary><?= esc(lang('EduTest.builder.workspace.about')) ?></summary><p><?= esc(lang('Player.ui.teacherDisclosure')) ?></p></details>
            </section>

            <aside id="builder-settings" class="settings-rail" aria-label="Quiz settings">
                <div class="rail-heading"><strong class="rail-title">Quiz settings</strong><span class="badge status" :class="quiz.status">{{ quiz.status }}</span></div>
                <div class="settings-content" ref="settingsPanel" tabindex="0" role="region" aria-label="<?= esc(lang('EduTest.builder.workspace.settings'), 'attr') ?>">
                    <details open><summary>Details</summary><div class="setting-group"><label class="form-field"><span class="form-label">Description</span><textarea class="form-control" v-model="quiz.description" rows="3"></textarea></label><label class="form-field"><span class="form-label">Instructions</span><textarea class="form-control" v-model="quiz.instructions" rows="3"></textarea></label><label class="form-field"><span class="form-label">Mode</span><select class="form-control" :value="quiz.mode" @change="changeMode($event)"><option value="assessment">Assessment</option><option value="practice">Practice</option></select></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.listed"><span><strong>List on public profile</strong><small>Takes effect when public profiles launch.</small></span></label></div></details>
                    <details><summary>Schedule & timing</summary><div class="setting-group"><label class="form-field"><span class="form-label">Opens ({{ quiz.timezone }})</span><input class="form-control" type="datetime-local" v-model="quiz.opensAtLocal"></label><label class="form-field"><span class="form-label">Closes ({{ quiz.timezone }})</span><input class="form-control" type="datetime-local" v-model="quiz.closesAtLocal"></label><label class="form-field"><span class="form-label">Total timer (minutes)</span><input class="form-control" type="number" min="0.5" max="1440" step="0.5" v-model="quiz.timeLimitMinutes" placeholder="No timer"></label></div></details>
                    <details v-if="quiz.mode === 'assessment'"><summary>Admission</summary><div class="setting-group"><label class="form-field"><span class="form-label">New passcode</span><input class="form-control" type="password" v-model="passcodeValue" @input="quiz.passcode.action='set'" autocomplete="new-password" placeholder="Leave unchanged"></label><button v-if="quiz.passcode.configured" class="btn btn-link btn-sm align-start" type="button" @click="clearPasscode">Clear current passcode</button><label class="form-field"><span class="form-label">Email</span><select class="form-control" v-model="quiz.emailMode"><option value="hidden">Hidden</option><option value="optional">Optional</option><option value="required">Required</option></select></label><label class="form-field"><span class="form-label">Phone</span><select class="form-control" v-model="quiz.phoneMode"><option value="hidden">Hidden</option><option value="optional">Optional</option><option value="required">Required</option></select></label></div></details>
                    <details><summary>Behavior</summary><div class="setting-group"><label class="form-check check-row"><input type="checkbox" v-model="quiz.shuffleQuestions"><span><strong>Shuffle questions</strong><small>Randomize order for each start.</small></span></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.shuffleOptions"><span><strong>Shuffle choices</strong><small>Randomize choice order.</small></span></label><label v-if="quiz.mode === 'assessment'" class="form-check check-row"><input type="checkbox" v-model="quiz.cheatCheck"><span><strong>Integrity monitoring</strong><small><?= esc(lang('EduTest.builder.integrityHelp')) ?></small></span></label></div></details>
                    <details><summary>Feedback & results</summary><div class="setting-group"><label class="form-field"><span class="form-label">Feedback timing</span><select class="form-control" v-model="quiz.feedback"><option value="at_end">At the end</option><option value="after_each">After each question</option></select></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.showScore"><span><strong>Show score</strong></span></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.showAnswers"><span><strong>Show correct answers</strong></span></label><label class="form-check check-row"><input type="checkbox" v-model="quiz.showExplain" :disabled="!quiz.showAnswers"><span><strong>Show explanations</strong></span></label></div></details>
                    <details open v-if="quiz.status !== 'draft'"><summary>Sharing</summary><div class="setting-group"><label class="form-field"><span class="form-label">Stable share link</span><input class="form-control" :value="quiz.shareUrl" readonly></label><button class="btn btn-default" type="button" @click="copyShare">Copy link</button></div></details>
                </div>
            </aside>
        </div>

        <dialog class="dialog app-dialog quiz-details-dialog" ref="detailsDialog" aria-labelledby="quiz-details-title" @close="detailsClosed">
            <form @submit.prevent="applyDetails">
                <div class="dialog-heading"><h2 id="quiz-details-title"><?= esc(lang('EduTest.builder.workspace.detailsTitle')) ?></h2><button class="btn btn-default btn-icon" type="button" @click="closeDetails" aria-label="<?= esc(lang('EduTest.builder.workspace.close'), 'attr') ?>">×</button></div>
                <p class="dialog-copy"><?= esc(lang('EduTest.builder.workspace.stagedHint')) ?></p>
                <label class="form-field"><span class="form-label"><?= esc(lang('EduTest.builder.workspace.title')) ?></span><input class="form-control" ref="detailsTitle" v-model="detailsTitle" maxlength="200" required></label>
                <label class="form-field"><span class="form-label"><?= esc(lang('EduTest.builder.workspace.cover')) ?></span><input class="form-control" type="file" accept="image/jpeg,image/png,image/webp" @change="pickDetailsCover" aria-describedby="details-cover-help"></label>
                <p class="dialog-copy" id="details-cover-help"><?= esc(lang('EduTest.builder.workspace.coverHelp')) ?></p>
                <img v-if="detailsCoverUrl" class="details-cover-preview" :src="detailsCoverUrl" alt="<?= esc(lang('EduTest.builder.workspace.coverPreview'), 'attr') ?>">
                <button v-if="detailsCoverUrl" class="btn btn-default btn-sm" type="button" @click="removeDetailsCover"><?= esc(lang('EduTest.builder.workspace.removeCover')) ?></button>
                <p v-if="detailsChecking" role="status"><?= esc(lang('EduTest.builder.workspace.checkingCover')) ?></p>
                <p v-if="detailsError" class="form-error" role="alert">{{ detailsError }}</p>
                <div class="dialog-actions"><button class="btn btn-default" type="button" @click="closeDetails"><?= esc(lang('EduTest.builder.workspace.cancel')) ?></button><button class="btn btn-primary" type="submit" :disabled="detailsChecking"><?= esc(lang('EduTest.builder.workspace.apply')) ?></button></div>
            </form>
        </dialog>

        <dialog class="dialog app-dialog" ref="conflictDialog" aria-label="<?= esc(lang('EduTest.builder.workspace.resolveConflict'), 'attr') ?>"><div class="dialog-heading"><h2>Newer changes found</h2></div><p class="dialog-copy">This quiz changed in another tab. Reload its latest version, or overwrite it with this tab’s draft.</p><div class="dialog-actions"><button class="btn btn-default" @click="reloadConflict">Reload latest</button><button class="btn btn-primary" @click="overwriteConflict">Overwrite</button></div></dialog>
    </template>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('js/vue.global.prod.js') . '?v=' . filemtime(FCPATH . 'js/vue.global.prod.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('js/builder.js') . '?v=' . filemtime(FCPATH . 'js/builder.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
