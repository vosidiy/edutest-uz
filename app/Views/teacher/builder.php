<?= $this->extend('layouts/teacher') ?>

<?= $this->section('head') ?>
<style>[v-cloak]{display:none!important}</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="quiz-builder" data-workspace-messages="<?= esc(json_encode(lang('EduTest.builder.workspace'), JSON_THROW_ON_ERROR), 'attr') ?>" data-public-id="<?= esc($quiz['publicId'], 'attr') ?>" v-cloak>
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
                        <span class="question-number">{{ index + 1 }}</span><span><strong>{{ question.content || 'Untitled question' }}</strong><small>Single choice · {{ question.optionsLayout }}</small></span>
                    </button>
                </div>

                <div class="add-question">
                    <button class="btn btn-default" type="button" @click="addQuestion">＋ Add question</button>
                </div>
            </aside>

            <section id="builder-editor" class="editor-canvas" ref="editorPanel" tabindex="0" aria-label="<?= esc(lang('EduTest.builder.workspace.editor'), 'attr') ?>">
                <div class="builder-notices mb-3">
                    <div v-if="quiz.mediaLimits.serverBytes < quiz.mediaLimits.audioBytes" class="alert alert-warning builder-banner">The current PHP upload limit is {{ formatBytes(quiz.mediaLimits.serverBytes) }}. Raise <code>upload_max_filesize</code> and <code>post_max_size</code> above 20 MiB to accept the full supported audio size.</div>

                    <div v-if="globalError" class="alert alert-danger builder-banner" role="alert">{{ globalError }} <button class="btn btn-link btn-sm" v-if="saveState === 'retry'" @click="save()">Retry</button>
                    </div>
                </div>
                <section v-if="selectedQuestion" class="card editor-paper">
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
                        
                        <div class="editor">
                            
                            <label class="form-field">
                                <h5 class="mb-2">Question</h5>
                                
                                <textarea placeholder="Type question text..." class="form-control" v-model="selectedQuestion.content" rows="2" maxlength="65535"></textarea>
                                
                            </label>
 
                        </div>

                        <div class="media-editor">       
                            <media-preview :media="selectedQuestion.media"></media-preview>
                            <div class="d-flex">
                                <div class="media-actions">
                                    <button class="btn btn-default" type="button" @click="chooseFile(selectedIndex,null)"> <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image preview-icon"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg> <span>Add media</span></button><button v-if="selectedQuestion.media" class="btn btn-red-subtle" type="button" @click="removeMedia(selectedIndex,null)">Remove</button>
                                </div>
                                <div class="ml-3">
                                    <p>Question media</p>
                                    <small class="text-secondary">One image or audio file.</small>
                                </div>
                            </div>
                        </div>

                        <div class="answer-editor mb-5">

                            <div class="section-label">
                                <h5>Answer choices</h5>
                                <div>
                                    <select style="opacity:0.7" v-model="selectedQuestion.optionsLayout"><option value="list">List view</option><option value="grid">Grid view</option>
                                    </select>
                                </div>
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
                                    
                                    <button class="btn btn-red-subtle btn-icon" type="button" @click="removeOption(index)" :disabled="selectedQuestion.options.length <= 2" :aria-label="`Remove answer ${optionLetter(index)}`">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x preview-icon"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                    </button>
                                </div>
                                <media-preview :media="option.media"></media-preview>
                                
                                <label class="form-check answer-correct-row"><input type="radio" :name="`correct-answer-${selectedIndex}`" :checked="option.isCorrect" @change="setCorrect(index,$event.target.checked)"><span>Mark as correct</span></label>
                            </div>
                            <button class="btn btn-neutral w-full" type="button" @click="addOption">
                                ＋ Add answer variant
                            </button>
                        </div>
                        <hr class="mt-6">
                        <label class="form-field mt-5">
                            <h5 class="mb-2">Explanation or feedback <span class="text-secondary">(optional)</span></h5>
                            <textarea placeholder="Type why certain answer is correct" class="form-control" v-model="selectedQuestion.explanation" rows="2" maxlength="900"></textarea>
                        </label>
                    </div>
                </section>
                <section v-else class="card editor-paper empty-state">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" color="currentColor" fill="none">
                            <path fill="currentColor" d="M12.056,1.25 C13.894,1.25 15.35,1.25 16.489,1.403 C17.661,1.561 18.61,1.893 19.359,2.641 C20.107,3.39 20.439,4.339 20.597,5.511 C20.75,6.65 20.75,8.106 20.75,9.944 L20.75,13.464 L20.75,13.465 C20.751,14.163 20.751,14.716 20.541,15.224 C20.33,15.732 19.939,16.123 19.444,16.617 L14.548,21.512 C14.122,21.939 13.786,22.276 13.358,22.481 C13.27,22.523 13.179,22.561 13.087,22.593 C12.64,22.751 12.164,22.751 11.56,22.75 L11.408,22.75 C9.826,22.75 8.573,22.75 7.581,22.634 C6.562,22.515 5.723,22.266 5.019,21.698 C4.755,21.485 4.515,21.245 4.302,20.981 C3.734,20.277 3.485,19.438 3.366,18.419 C3.25,17.427 3.25,16.174 3.25,14.592 L3.25,9.943 C3.25,8.106 3.25,6.65 3.403,5.511 C3.561,4.339 3.893,3.39 4.641,2.641 C5.39,1.893 6.339,1.561 7.511,1.403 C8.65,1.25 10.106,1.25 11.944,1.25 Z M7.711,2.89 C6.705,3.025 6.125,3.279 5.702,3.702 C5.279,4.125 5.025,4.705 4.89,5.711 C4.752,6.739 4.75,8.093 4.75,10 L4.75,14.544 C4.75,16.185 4.751,17.35 4.856,18.245 C4.958,19.124 5.152,19.645 5.47,20.04 C5.615,20.22 5.78,20.385 5.96,20.53 C6.355,20.848 6.876,21.042 7.755,21.144 C8.65,21.249 9.815,21.25 11.456,21.25 C11.842,21.25 12.083,21.247 12.25,21.237 L12.25,20.945 C12.25,19.577 12.25,18.475 12.366,17.608 C12.488,16.708 12.746,15.95 13.348,15.348 C13.95,14.746 14.708,14.488 15.608,14.366 C16.475,14.25 17.577,14.25 18.945,14.25 L19.233,14.25 C19.247,14.058 19.25,13.781 19.25,13.343 L19.25,10 C19.25,8.093 19.248,6.739 19.11,5.711 C18.975,4.705 18.721,4.125 18.298,3.702 C17.875,3.279 17.295,3.025 16.289,2.89 C15.262,2.752 13.907,2.75 12,2.75 C10.093,2.75 8.739,2.752 7.711,2.89 Z M7.25,7 C7.25,6.586 7.586,6.25 8,6.25 L16,6.25 C16.414,6.25 16.75,6.586 16.75,7 C16.75,7.414 16.414,7.75 16,7.75 L8,7.75 C7.586,7.75 7.25,7.414 7.25,7 Z M18.188,15.751 C17.174,15.755 16.412,15.772 15.808,15.853 C15.074,15.952 14.686,16.132 14.409,16.409 C14.132,16.686 13.952,17.074 13.853,17.808 C13.772,18.412 13.755,19.174 13.751,20.188 Z M7.25,11 C7.25,10.586 7.586,10.25 8,10.25 L12,10.25 C12.414,10.25 12.75,10.586 12.75,11 C12.75,11.414 12.414,11.75 12,11.75 L8,11.75 C7.586,11.75 7.25,11.414 7.25,11 Z"></path>
                        </svg>
                    </span>
                    <h5>Start with a question</h5>
                    <p> 👈 Add a question from the left rail.</p>

                    <div>
                        <p>OR</p>
                        <button type="button" @click="addQuestion" class="btn btn-primary"> 
                            Add first question
                        </button>
                    </div>
                </section>
                
            </section>

            <aside id="builder-settings" class="settings-rail" aria-label="Quiz settings">
                <div class="rail-heading">
                    <strong class="rail-title">Quiz settings</strong>
                    <label class="form-switch">
                        <span class="label-switch">{{ quiz.status === 'published' ? 'Active' : 'Draft' }}</span>
                        <input class="input-switch" type="checkbox" role="switch" :checked="quiz.status === 'published'" @change="setActive($event.target.checked)">
                        <span class="slider" aria-hidden="true"></span>
                    </label>
                </div>
                <div class="settings-content" ref="settingsPanel" tabindex="0" role="region" aria-label="<?= esc(lang('EduTest.builder.workspace.settings'), 'attr') ?>">
                    <details open>
                        <summary>Details</summary>
                        <div class="setting-group">
                            <label class="form-field"><span class="form-label">Description</span><textarea placeholder="About quiz" class="form-control" v-model="quiz.description" rows="3"></textarea>
                            </label>
                            
                        </div>
                    </details>

                    <details>
                        <summary>Timer & admission</summary>
                        <div class="setting-group">
                            <label class="form-field mb-3"><span class="form-label">Total timer (minutes)</span><input placeholder="Minutes" class="form-control" type="number" min="1" max="120" step="1" v-model.number="quiz.timeLimitMinutes"></label>
                            <label class="form-check check-row"><input type="checkbox" v-model="quiz.askEmail"><span><strong>Ask for email</strong><small>The field remains optional for students.</small></span></label>
                            <label class="form-check check-row"><input type="checkbox" v-model="quiz.askPhone"><span><strong>Ask for phone</strong><small>The field remains optional for students.</small></span></label>
                        </div>
                    </details>
                    <details>
                        <summary>Question selection</summary>
                        <div class="setting-group">
                            <label class="form-field mb-3"><span class="form-label">Questions per attempt</span><input class="form-control" type="number" min="1" :max="quiz.questions.length || null" step="1" v-model.number="quiz.drawCount" placeholder="All questions"></label>
                          
                            <label class="form-check check-row"><input type="checkbox" v-model="quiz.shuffleQuestions"><span><strong>Shuffle questions</strong>
                            <small>Randomize order for each start.</small></span></label>
                            
                            <label class="form-check check-row"><input type="checkbox" v-model="quiz.shuffleOptions"><span><strong>Shuffle choices</strong><small>Randomize choice order.</small></span></label>
                            
                        </div>
                    </details>

                    <details><summary>Feedback & results</summary>
                        <div class="setting-group">
                            <label class="form-field">
                                <span class="form-label">Feedback timing</span>
                                <select class="form-control" v-model="quiz.feedback"><option value="at_end">At the end</option><option value="after_each">After each question</option>
                                </select>
                            </label>
                            
                            <label class="form-check check-row"><input type="checkbox" v-model="quiz.showScore"><span><strong>Show score</strong></span>
                            </label>
                            
                            <label class="form-check check-row">
                                <input type="checkbox" v-model="quiz.showAnswers"><span><strong>Show correct answers</strong></span>
                            </label>
                            
                            <label class="form-check check-row">
                                <input type="checkbox" v-model="quiz.showExplain" :disabled="!quiz.showAnswers"><span><strong>Show explanations</strong></span>
                            </label>
                        </div>
                    </details>
                    
                    <details open v-if="quiz.status === 'published'">
                        <summary>Sharing</summary>
                        <div class="setting-group">
                            <label class="form-field"><span class="form-label">Stable share link</span><input class="form-control" :value="quiz.shareUrl" readonly></label><button class="btn btn-default" type="button" @click="copyShare">Copy link</button>
                        </div>
                    </details>
                </div>
            </aside>
        </div>

        <dialog class="dialog app-dialog quiz-details-dialog" ref="detailsDialog" aria-labelledby="quiz-details-title" @close="detailsClosed">
            <form @submit.prevent="applyDetails">
                <header class="dialog-heading">
                    <h4 id="quiz-details-title"><?= esc(lang('EduTest.builder.workspace.detailsTitle')) ?></h4>
                    <button class="btn btn-default btn-icon" type="button" @click="closeDetails">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x preview-icon"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </header>

                <label class="form-field mb-4"><span class="form-label"><?= esc(lang('EduTest.builder.workspace.title')) ?></span><input class="form-control" ref="detailsTitle" v-model="detailsTitle" maxlength="200" required></label>
                <label class="form-field"><span class="form-label"><?= esc(lang('EduTest.builder.workspace.cover')) ?></span><input class="form-control" type="file" accept="image/jpeg,image/png,image/webp" @change="pickDetailsCover" aria-describedby="details-cover-help"></label>
                <p class="dialog-copy" id="details-cover-help"><?= esc(lang('EduTest.builder.workspace.coverHelp')) ?></p>
                
                <div style="position:relative">
                    <img style="max-height:300px; object-fit:cover" v-if="detailsCoverUrl" class="details-cover-preview" :src="detailsCoverUrl" alt="<?= esc(lang('EduTest.builder.workspace.coverPreview'), 'attr') ?>">
                
                    <button v-if="detailsCoverUrl" class="btn btn-default" style="position:absolute; bottom:4px; right:4px" type="button" @click="removeDetailsCover"><?= esc(lang('EduTest.builder.workspace.removeCover')) ?></button>
                </div>

                <p v-if="detailsChecking" role="status"><?= esc(lang('EduTest.builder.workspace.checkingCover')) ?></p>
                <p v-if="detailsError" class="form-error" role="alert">{{ detailsError }}</p>
                <div class="dialog-actions"><button class="btn btn-default" type="button" @click="closeDetails"><?= esc(lang('EduTest.builder.workspace.cancel')) ?></button><button class="btn btn-primary" type="submit" :disabled="detailsChecking"><?= esc(lang('EduTest.builder.workspace.apply')) ?></button></div>
            </form>
        </dialog>

    </template>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('js/vue.global.prod.js') . '?v=' . filemtime(FCPATH . 'js/vue.global.prod.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('js/builder.js') . '?v=' . filemtime(FCPATH . 'js/builder.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
