(() => {
  'use strict';

  const BuilderRules = Object.freeze({
    editable(quiz, passcodeValue = '') {
      if (!quiz) return '';
      const fields = ['title', 'description', 'instructions', 'mode', 'listed', 'timeLimitMinutes', 'opensAtLocal', 'closesAtLocal',
        'emailMode', 'phoneMode', 'shuffleQuestions', 'shuffleOptions', 'feedback', 'showScore', 'showAnswers', 'showExplain', 'cheatCheck'];
      const value = Object.fromEntries(fields.map(key => [key, quiz[key]]));
      value.passcode = {action: quiz.passcode?.action || 'unchanged', value: quiz.passcode?.action === 'set' ? passcodeValue : ''};
      value.questions = (quiz.questions || []).map(q => ({id:q.id, type:q.type, content:q.content, explanation:q.explanation,
        textAnswers:q.textAnswers, options:q.options.map(o => ({id:o.id, content:o.content, isCorrect:o.isCorrect}))}));
      return JSON.stringify(value);
    },
    saveButton(state, busy, dirty) {
      if (busy) return {label:'saving', disabled:true, primary:false};
      if (state === 'conflict') return {label:'resolveConflict', disabled:false, primary:true};
      return {label:dirty ? 'saveChanges' : 'saved', disabled:!dirty, primary:dirty};
    },
    shouldAutosave(quiz) { return quiz?.status === 'draft'; },
    modeTransition(currentMode, nextMode, confirmChange) {
      if (!['assessment', 'practice'].includes(nextMode) || nextMode === currentMode) {
        return {accepted: false, mode: currentMode, clearAssessmentSettings: false};
      }
      if (!confirmChange(nextMode)) {
        return {accepted: false, mode: currentMode, clearAssessmentSettings: false};
      }
      return {accepted: true, mode: nextMode, clearAssessmentSettings: nextMode === 'practice'};
    },
    publicationIssues(quiz, messages = {}) {
      const message = (key, number = null) => String(messages[key] || key).replace('{number}', String(number ?? ''));
      const issues = [];
      if (!quiz || String(quiz.title || '').trim() === '') issues.push(message('title'));
      const questions = Array.isArray(quiz?.questions) ? quiz.questions : [];
      if (questions.length === 0) issues.push(message('questions'));
      questions.forEach((question, index) => {
        const number = index + 1;
        if (String(question?.content || '').trim() === '') issues.push(message('questionText', number));
        if (question?.type === 'short_text') {
          if (!(question.textAnswers || []).some(answer => String(answer || '').trim() !== '')) issues.push(message('acceptedAnswer', number));
          return;
        }
        const options = Array.isArray(question?.options) ? question.options : [];
        if (options.length < 2) issues.push(message('twoChoices', number));
        if (options.some(option => String(option?.content || '').trim() === '' && !option?.media)) issues.push(message('emptyChoice', number));
        const correct = options.filter(option => option?.isCorrect).length;
        if (question?.type === 'single_choice' && correct !== 1) issues.push(message('singleCorrect', number));
        if (question?.type === 'multi_select' && correct < 1) issues.push(message('multiCorrect', number));
      });
      return issues;
    }
  });
  window.EduTestBuilderRules = BuilderRules;

  const root = document.querySelector('#quiz-builder');
  if (!root || !window.Vue || !window.EduTestApi) return;

  const MediaPreview = {
    props: { media: { type: Object, default: null } },
    methods: {
      directVideo(url) { try { return new URL(url).pathname.toLowerCase().endsWith('.mp4'); } catch (_) { return false; } }
    },
    template: `<div v-if="media" class="media-preview">
      <img v-if="media.type==='image'" :src="media.url" alt="Question media">
      <audio v-else-if="media.type==='audio'" :src="media.url" controls preload="metadata"></audio>
      <video v-else-if="directVideo(media.embedUrl || media.url)" :src="media.embedUrl || media.url" controls preload="metadata"></video>
      <iframe v-else :src="media.embedUrl" title="Question video" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>
    </div>`
  };

  const app = Vue.createApp({
    components: { MediaPreview },
    data() {
      return {
        publicId: root.dataset.publicId,
        quiz: null,
        loading: true,
        fatalError: '',
        globalError: '',
        fieldErrors: {},
        selectedIndex: 0,
        preview: false,
        saving: false,
        saveState: 'saved',
        lastSavedAt: null,
        autosaveTimer: null,
        hydrating: true,
        passcodeValue: '',
        savedFingerprint: '',
        savePromise: null,
        mediaBusy: false,
        activePanel: 'editor',
        pendingCover: null,
        detailsOpen: false,
        detailsTitle: '',
        detailsCover: null,
        detailsChecking: false,
        detailsError: '',
        coverUrls: new Set(),
        workspaceMessages: JSON.parse(root.dataset.workspaceMessages || '{}'),
        conflictVersion: null,
        publicationMessages: JSON.parse(root.dataset.publicationMessages || '{}'),
        modeMessages: JSON.parse(root.dataset.modeMessages || '{}')
      };
    },
    computed: {
      editableFingerprint() { return BuilderRules.editable(this.quiz, this.passcodeValue); },
      hasChanges() { return !!this.quiz && (this.editableFingerprint !== this.savedFingerprint || this.pendingCover !== null); },
      saveButton() { return BuilderRules.saveButton(this.saveState, this.saving || this.mediaBusy, this.hasChanges); },
      detailsCoverUrl() {
        if (this.detailsCover?.action === 'remove') return null;
        return this.detailsCover?.url || this.quiz?.cover?.url || null;
      },
      selectedQuestion() { return this.quiz?.questions?.[this.selectedIndex] || null; },
      publicationIssues() { return BuilderRules.publicationIssues(this.quiz, this.publicationMessages); },
      publishReady() { return this.publicationIssues.length === 0; },
      publishReadinessMessage() { return this.publicationIssues[0] || ''; },
      saveLabel() {
        if (this.saving || this.mediaBusy) return this.workspaceMessages.saving;
        if (this.saveState === 'dirty') return 'Unsaved changes';
        if (this.saveState === 'retry') return 'Save failed — retry';
        if (this.saveState === 'validation') return 'Fix validation errors';
        if (this.saveState === 'conflict') return 'Newer changes found';
        if (this.lastSavedAt) return `Saved ${new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(this.lastSavedAt)}`;
        return this.quiz?.status === 'draft' ? 'Draft loaded' : 'Quiz loaded';
      }
    },
    watch: {
      editableFingerprint() {
        if (!this.hydrating && !this.loading) this.markDirty();
      },
      globalError(value) { if (value) this.revealError(); }
    },
    async mounted() {
      window.addEventListener('beforeunload', this.beforeUnload);
      await this.load();
    },
    beforeUnmount() {
      window.removeEventListener('beforeunload', this.beforeUnload);
      clearTimeout(this.autosaveTimer);
      for (const url of this.coverUrls) URL.revokeObjectURL(url);
    },
    methods: {
      async load() {
        this.loading = true;
        this.fatalError = '';
        try {
          const response = await EduTestApi.request('/api/v1/quizzes/' + this.publicId);
          this.pendingCover = null;
          this.cleanCoverUrls();
          this.applyServerQuiz(response.data.quiz);
          this.saveState = 'saved';
          this.conflictVersion = null;
          this.fieldErrors = {};
          this.globalError = '';
        } catch (error) {
          this.fatalError = error.message;
        } finally {
          this.loading = false;
          this.$nextTick(() => { this.hydrating = false; });
        }
      },
      applyServerQuiz(quiz) {
        clearTimeout(this.autosaveTimer);
        this.hydrating = true;
        this.quiz = quiz;
        this.savedFingerprint = BuilderRules.editable(quiz);
        this.selectedIndex = Math.max(0, Math.min(this.selectedIndex, quiz.questions.length - 1));
        this.passcodeValue = '';
        this.$nextTick(() => { this.hydrating = false; });
      },
      scheduleAutosave() {
        clearTimeout(this.autosaveTimer);
        if (!this.saving && !this.mediaBusy && this.hasChanges && this.saveState === 'dirty' && BuilderRules.shouldAutosave(this.quiz)) {
          this.autosaveTimer = setTimeout(() => this.save(false), 1000);
        }
      },
      markDirty() {
        if (this.saveState === 'conflict') return;
        if (!this.saving) this.saveState = this.hasChanges ? 'dirty' : 'saved';
        this.globalError = '';
        this.scheduleAutosave();
      },
      async acceptSaved(server, sent, references, sentPasscode) {
        // Keep the same reactive row objects: a new ID must follow its row even if reordered/deleted during the request.
        this.hydrating = true;
        const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
        for (const key of Object.keys(server)) {
          if (['questions', 'passcode'].includes(key)) continue;
          if (['version', 'revision', 'status', 'resultsAvailable', 'resultsUrl', 'updatedAt', 'hasPublished', 'publishedRevision', 'hasUnpublishedChanges', 'publishedMode'].includes(key) || same(this.quiz[key], sent[key])) this.quiz[key] = server[key];
        }
        if (same(this.quiz.passcode, sent.passcode) && this.passcodeValue === sentPasscode) {
          this.quiz.passcode = server.passcode;
          this.passcodeValue = '';
        } else {
          this.quiz.passcode.configured = server.passcode.configured;
        }
        references.forEach(({question, options}, index) => {
          const remote = server.questions[index], before = sent.questions[index];
          if (!remote || !this.quiz.questions.includes(question)) return;
          question.id = remote.id;
          for (const key of ['type', 'content', 'explanation', 'textAnswers', 'media']) {
            if (same(question[key], before[key])) question[key] = remote[key];
          }
          options.forEach((option, optionIndex) => {
            const remoteOption = remote.options[optionIndex], beforeOption = before.options[optionIndex];
            if (!remoteOption || !question.options.includes(option)) return;
            option.id = remoteOption.id;
            option.code = remoteOption.code;
            for (const key of ['content', 'isCorrect', 'media']) {
              if (same(option[key], beforeOption[key])) option[key] = remoteOption[key];
            }
          });
        });
        this.savedFingerprint = BuilderRules.editable(server);
        await this.$nextTick();
        this.hydrating = false;
      },
      save(manual = false, overwrite = false) {
        if (this.savePromise) return this.savePromise;
        if (this.mediaBusy) return Promise.resolve(false);
        if (this.saveState === 'conflict' && !overwrite) {
          if (manual) this.openConflict();
          return Promise.resolve(false);
        }
        if (!this.quiz || (!this.hasChanges && !overwrite)) return Promise.resolve(true);
        this.savePromise = this.performSave(overwrite).finally(() => { this.savePromise = null; });
        return this.savePromise;
      },
      async performSave(overwrite) {
        clearTimeout(this.autosaveTimer);
        this.saving = true;
        this.saveState = 'saving';
        this.globalError = '';
        this.fieldErrors = {};
        const sent = JSON.parse(JSON.stringify(this.quiz));
        const sentPasscode = this.passcodeValue;
        const references = this.quiz.questions.map(question => ({question, options:[...question.options]}));
        const cover = this.pendingCover;
        let contentSaved = this.editableFingerprint === this.savedFingerprint;
        try {
          if (!contentSaved || overwrite) {
            const payload = {...sent, passcode:{...sent.passcode, value:sentPasscode}};
            const response = await EduTestApi.request('/api/v1/quizzes/' + this.publicId + (overwrite ? '?overwrite=1' : ''), {method:'PUT', body:JSON.stringify(payload)});
            await this.acceptSaved(response.data.quiz, sent, references, sentPasscode);
            contentSaved = true;
          }
          if (cover && this.pendingCover === cover) {
            const endpoint = '/api/v1/quizzes/' + this.publicId + '/cover';
            let options, url = endpoint;
            if (cover.action === 'remove') {
              url += '?version=' + this.quiz.version;
              options = {method:'DELETE', body:'{}'};
            } else {
              const form = new FormData();
              form.append('media', cover.file);
              form.append('version', String(this.quiz.version));
              options = {method:'POST', body:form};
            }
            const response = await EduTestApi.request(url, options);
            this.quiz.version = response.data.version;
            this.quiz.cover = response.data.media;
            if (this.pendingCover === cover) this.pendingCover = null;
            if (this.detailsCover === cover) this.detailsCover = null;
            this.cleanCoverUrls();
          }
          this.lastSavedAt = new Date();
          this.saveState = this.hasChanges ? 'dirty' : 'saved';
          return !this.hasChanges;
        } catch (error) {
          this.handleSaveError(error);
          if (cover && contentSaved && error.code !== 'version_conflict') this.globalError = this.workspaceMessages.coverFailed + ' ' + error.message;
          return false;
        } finally {
          this.saving = false;
          this.scheduleAutosave();
        }
      },
      handleSaveError(error) {
        this.globalError = error.message;
        if (error.code === 'version_conflict') {
          this.saveState = 'conflict';
          this.conflictVersion = Number(error.fields?.version || 0);
          this.openConflict();
        } else {
          this.fieldErrors = error.fields || {};
          this.saveState = error.status === 422 ? 'validation' : 'retry';
        }
      },
      openConflict() {
        if (!this.$refs.conflictDialog?.open) this.$refs.conflictDialog?.showModal();
      },
      async reloadConflict() {
        this.$refs.conflictDialog?.close();
        await this.load();
      },
      async overwriteConflict() {
        this.$refs.conflictDialog?.close();
        if (this.conflictVersion) this.quiz.version = this.conflictVersion;
        await this.save(true, true);
      },
      beforeUnload(event) {
        if (!this.hasChanges && !this.saving && !this.mediaBusy && this.saveState !== 'conflict') return;
        event.preventDefault();
        event.returnValue = '';
      },
      showPanel(panel, focus = true) {
        this.activePanel = panel;
        if (focus) this.$nextTick(() => this.$refs[panel + 'Panel']?.focus({preventScroll:true}));
      },
      selectQuestion(index) {
        this.selectedIndex = index;
        this.showPanel('editor');
        this.$nextTick(() => { if (this.$refs.editorPanel) this.$refs.editorPanel.scrollTop = 0; });
      },
      revealError() {
        this.showPanel('editor', false);
        this.$nextTick(() => { if (this.$refs.editorPanel) this.$refs.editorPanel.scrollTop = 0; });
      },
      openDetails() {
        this.detailsTitle = this.quiz.title;
        this.detailsCover = this.pendingCover;
        this.detailsError = '';
        this.detailsChecking = false;
        this.detailsOpen = true;
        this.cleanCoverUrls();
        this.$refs.detailsDialog.showModal();
        this.$nextTick(() => this.$refs.detailsTitle.focus());
      },
      closeDetails() { this.$refs.detailsDialog?.close(); },
      detailsClosed() {
        // Native close events are queued: a quick reopen must not clear the new dialog state.
        if (this.$refs.detailsDialog?.open) return;
        this.detailsOpen = false;
        this.detailsCover = null;
        this.detailsChecking = false;
        this.cleanCoverUrls();
        this.$refs.editTitle?.focus();
      },
      cleanCoverUrls() {
        const retained = [this.pendingCover?.url, this.detailsOpen ? this.detailsCover?.url : null];
        for (const url of this.coverUrls) {
          if (!retained.includes(url)) { URL.revokeObjectURL(url); this.coverUrls.delete(url); }
        }
      },
      async pickDetailsCover(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        this.detailsError = '';
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
          this.detailsError = this.workspaceMessages.invalidCover;
          return;
        }
        const url = URL.createObjectURL(file);
        this.coverUrls.add(url);
        const candidate = {action:'upload', file, url};
        const previous = this.detailsCover;
        this.detailsCover = candidate;
        this.detailsChecking = true;
        const image = new Image();
        const valid = await new Promise(resolve => {
          image.onload = () => resolve(image.naturalWidth > 0 && image.naturalHeight > 0 && image.naturalWidth * image.naturalHeight <= 16000000);
          image.onerror = () => resolve(false);
          image.src = url;
        });
        if (this.detailsOpen && this.detailsCover?.url === candidate.url) {
          this.detailsChecking = false;
          if (!valid) { this.detailsCover = previous; this.detailsError = this.workspaceMessages.invalidCover; }
        }
        this.cleanCoverUrls();
      },
      removeDetailsCover() {
        this.detailsCover = this.quiz.cover || this.saving ? {action:'remove'} : null;
        this.detailsChecking = false;
        this.detailsError = '';
        this.cleanCoverUrls();
      },
      applyDetails() {
        const title = this.detailsTitle.trim();
        if (!title || [...title].length > 200) { this.detailsError = this.workspaceMessages.invalidTitle; return; }
        if (this.detailsChecking) return;
        this.quiz.title = title;
        this.pendingCover = this.detailsCover;
        this.markDirty();
        this.closeDetails();
      },
      typeLabel(type) {
        return { single_choice: 'Single choice', multi_select: 'Multi-select', short_text: 'Short text' }[type] || 'Question';
      },
      formatBytes(bytes) {
        if (!Number.isFinite(Number(bytes))) return 'unlimited';
        return `${Math.floor(Number(bytes) / 1048576)} MiB`;
      },
      fieldError(path) { return this.fieldErrors?.[path] || ''; },
      optionLetter(index) {
        let value = index + 1;
        let label = '';
        while (value > 0) {
          value--;
          label = String.fromCharCode(65 + (value % 26)) + label;
          value = Math.floor(value / 26);
        }
        return label;
      },
      addQuestion() {
        const options = [{ id: null, content: '', isCorrect: true, media: null }, { id: null, content: '', isCorrect: false, media: null }];
        this.quiz.questions.push({ id: null, position: this.quiz.questions.length + 1, type: 'single_choice', content: '', explanation: '', textAnswers: [], media: null, options });
        this.selectQuestion(this.quiz.questions.length - 1);
        this.preview = false;
      },
      removeQuestion() {
        if (!this.selectedQuestion || !window.confirm('Delete this question and its media?')) return;
        this.quiz.questions.splice(this.selectedIndex, 1);
        this.selectedIndex = Math.max(0, Math.min(this.selectedIndex, this.quiz.questions.length - 1));
      },
      moveQuestion(direction) {
        const target = this.selectedIndex + direction;
        if (target < 0 || target >= this.quiz.questions.length) return;
        const [question] = this.quiz.questions.splice(this.selectedIndex, 1);
        this.quiz.questions.splice(target, 0, question);
        this.selectedIndex = target;
      },
      changeQuestionType(type) {
        const question = this.selectedQuestion;
        if (!question || type === question.type) return;
        question.type = type;
        if (type === 'short_text') {
          question.options = [];
          question.textAnswers = [''];
        } else {
          question.textAnswers = [];
          question.options = [{ id: null, content: '', isCorrect: true, media: null }, { id: null, content: '', isCorrect: false, media: null }];
        }
      },
      addOption() { this.selectedQuestion.options.push({ id: null, content: '', isCorrect: false, media: null }); },
      removeOption(index) {
        const option = this.selectedQuestion.options[index];
        if (option?.media && !window.confirm('Delete this answer and its attached media?')) return;
        this.selectedQuestion.options.splice(index, 1);
      },
      setCorrect(index, checked) {
        if (this.selectedQuestion.type === 'single_choice') {
          if (!checked) return;
          this.selectedQuestion.options.forEach((option, i) => { option.isCorrect = i === index; });
        } else this.selectedQuestion.options[index].isCorrect = checked;
      },
      changeMode(event) {
        const select = event?.target;
        const mode = String(select?.value || '');
        const previousMode = this.quiz.mode;
        const transition = BuilderRules.modeTransition(previousMode, mode, nextMode => (
          window.confirm(this.modeMessages[nextMode] || 'Change the mode for future starts?')
        ));
        if (!transition.accepted) {
          if (select) select.value = previousMode;
          return;
        }
        this.quiz.mode = transition.mode;
        if (transition.clearAssessmentSettings) {
          this.quiz.passcode.action = 'clear';
          this.quiz.passcode.configured = false;
          this.passcodeValue = '';
          this.quiz.emailMode = 'hidden';
          this.quiz.phoneMode = 'hidden';
          this.quiz.cheatCheck = false;
        }
      },
      clearPasscode() {
        this.quiz.passcode.action = 'clear';
        this.quiz.passcode.configured = false;
        this.passcodeValue = '';
      },
      targetAt(questionIndex, optionIndex) {
        const question = this.quiz.questions[questionIndex];
        return optionIndex === null ? question : question?.options?.[optionIndex];
      },
      targetEndpoint(questionIndex, optionIndex) {
        if (questionIndex === -1) return `/api/v1/quizzes/${this.publicId}/cover`;
        const target = this.targetAt(questionIndex, optionIndex);
        if (!target?.id) return null;
        return optionIndex === null
          ? `/api/v1/quizzes/${this.publicId}/questions/${target.id}/media`
          : `/api/v1/quizzes/${this.publicId}/options/${target.id}/media`;
      },
      async prepareMedia(questionIndex, optionIndex) {
        if (this.mediaBusy || !await this.save(true)) return null;
        if (this.hasChanges) { this.globalError = this.workspaceMessages.unsavedMedia; return null; }
        const endpoint = this.targetEndpoint(questionIndex, optionIndex);
        if (!endpoint) {
          this.globalError = 'Save the question before adding media.';
          return null;
        }
        return endpoint;
      },
      chooseFile(questionIndex, optionIndex) {
        const input = document.createElement('input');
        input.type = 'file';
        const imageOnly = questionIndex === -1 || optionIndex !== null;
        input.accept = imageOnly ? 'image/jpeg,image/png,image/webp' : 'image/jpeg,image/png,image/webp,audio/mpeg,audio/mp4,audio/ogg,audio/wav';
        input.addEventListener('change', async () => {
          if (!input.files?.[0]) return;
          const file = input.files[0];
          const appLimit = file.type.startsWith('image/') ? this.quiz.mediaLimits.imageBytes : this.quiz.mediaLimits.audioBytes;
          if (file.size > appLimit) {
            this.globalError = `This file exceeds the ${this.formatBytes(appLimit)} ${file.type.startsWith('image/') ? 'image' : 'audio'} limit.`;
            return;
          }
          if (file.size + 65536 > this.quiz.mediaLimits.serverBytes) {
            this.globalError = `The current PHP upload limit is too low for this file. Increase upload_max_filesize and post_max_size.`;
            return;
          }
          const endpoint = await this.prepareMedia(questionIndex, optionIndex);
          if (!endpoint) return;
          const form = new FormData();
          form.append('media', file);
          form.append('version', String(this.quiz.version));
          await this.runMedia(endpoint, { method: 'POST', body: form }, questionIndex, optionIndex);
        });
        input.click();
      },
      async addVideo(questionIndex, optionIndex) {
        const videoUrl = window.prompt('Paste a YouTube, Vimeo, or direct HTTPS MP4 URL:');
        if (!videoUrl) return;
        const endpoint = await this.prepareMedia(questionIndex, optionIndex);
        if (!endpoint) return;
        await this.runMedia(endpoint, { method: 'POST', body: JSON.stringify({ videoUrl, version: this.quiz.version }) }, questionIndex, optionIndex);
      },
      async removeMedia(questionIndex, optionIndex) {
        if (!window.confirm('Remove this media?')) return;
        const endpoint = await this.prepareMedia(questionIndex, optionIndex);
        if (!endpoint) return;
        await this.runMedia(`${endpoint}?version=${this.quiz.version}`, { method: 'DELETE', body: '{}' }, questionIndex, optionIndex);
      },
      async runMedia(endpoint, options, questionIndex, optionIndex) {
        this.globalError = '';
        if (this.mediaBusy) return;
        this.mediaBusy = true;
        clearTimeout(this.autosaveTimer);
        const target = this.targetAt(questionIndex, optionIndex);
        try {
          const response = await EduTestApi.request(endpoint, options);
          this.quiz.version = response.data.version;
          if (target) target.media = response.data.media;
          this.lastSavedAt = new Date();
          this.saveState = this.hasChanges ? 'dirty' : 'saved';
        } catch (error) {
          this.handleSaveError(error);
        }
        finally { this.mediaBusy = false; this.scheduleAutosave(); }
      },
      async lifecycle(action) {
        if (this.mediaBusy || this.saving) return;
        if (action === 'publish' && !this.publishReady) {
          this.globalError = this.publishReadinessMessage;
          this.saveState = 'validation';
          return;
        }
        if (!await this.save(true)) return;
        if (!window.confirm(action === 'publish' ? (this.quiz.hasPublished ? 'Publish these saved changes for future starts?' : 'Publish this quiz and activate its stable share page?') : `${action} this quiz?`)) return;
        const sent = JSON.parse(JSON.stringify(this.quiz));
        const sentPasscode = this.passcodeValue;
        const references = this.quiz.questions.map(question => ({question, options:[...question.options]}));
        this.mediaBusy = true;
        clearTimeout(this.autosaveTimer);
        try {
          const response = await EduTestApi.request(`/api/v1/quizzes/${this.publicId}/${action}`, { method: 'POST', body: '{}' });
          await this.acceptSaved(response.data.quiz, sent, references, sentPasscode);
          this.saveState = this.hasChanges ? 'dirty' : 'saved';
          EduTestApi.toast(action === 'publish' ? (sent.hasPublished ? 'Quiz changes published.' : 'Quiz published.') : 'Quiz updated.');
        } catch (error) {
          this.fieldErrors = error.fields || {};
          this.globalError = error.message;
          this.saveState = error.status === 422 ? 'validation' : 'retry';
        } finally {
          this.mediaBusy = false;
          this.scheduleAutosave();
        }
      },
      async copyShare() {
        try {
          await navigator.clipboard.writeText(this.quiz.shareUrl);
          EduTestApi.toast('Share link copied.');
        } catch (_) {
          window.prompt('Copy this quiz link:', this.quiz.shareUrl);
        }
      }
    }
  });

  app.mount(root);
})();
