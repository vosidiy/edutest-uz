(() => {
  'use strict';

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
        coverUploading: false,
        conflictVersion: null
      };
    },
    computed: {
      selectedQuestion() { return this.quiz?.questions?.[this.selectedIndex] || null; },
      saveLabel() {
        if (this.saveState === 'saving') return 'Saving…';
        if (this.saveState === 'dirty') return 'Unsaved changes';
        if (this.saveState === 'retry') return 'Save failed — retry';
        if (this.saveState === 'validation') return 'Fix validation errors';
        if (this.saveState === 'conflict') return 'Newer changes found';
        if (this.lastSavedAt) return `Saved ${new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(this.lastSavedAt)}`;
        return 'Draft loaded';
      }
    },
    watch: {
      quiz: {
        deep: true,
        handler() {
          if (this.hydrating || this.loading || !this.quiz) return;
          this.markDirty();
        }
      }
    },
    async mounted() {
      window.addEventListener('beforeunload', this.beforeUnload);
      await this.load();
    },
    beforeUnmount() {
      window.removeEventListener('beforeunload', this.beforeUnload);
      clearTimeout(this.autosaveTimer);
    },
    methods: {
      async load() {
        this.loading = true;
        this.fatalError = '';
        try {
          const response = await EduTestApi.request(`/api/v1/quizzes/${this.publicId}`);
          this.applyServerQuiz(response.data.quiz);
          this.saveState = 'saved';
        } catch (error) {
          this.fatalError = error.message;
        } finally {
          this.loading = false;
          this.$nextTick(() => { this.hydrating = false; });
        }
      },
      applyServerQuiz(quiz) {
        this.hydrating = true;
        this.quiz = quiz;
        this.selectedIndex = Math.max(0, Math.min(this.selectedIndex, quiz.questions.length - 1));
        this.passcodeValue = '';
        this.$nextTick(() => { this.hydrating = false; });
      },
      markDirty() {
        if (this.saveState === 'conflict') return;
        this.saveState = 'dirty';
        this.globalError = '';
        clearTimeout(this.autosaveTimer);
        this.autosaveTimer = setTimeout(() => this.save(false), 1000);
      },
      async save(manual = false, overwrite = false) {
        if (!this.quiz || (this.saveState === 'saved' && !manual && !overwrite)) return true;
        clearTimeout(this.autosaveTimer);
        this.saving = true;
        this.saveState = 'saving';
        this.globalError = '';
        this.fieldErrors = {};
        const payload = JSON.parse(JSON.stringify(this.quiz));
        payload.passcode = { ...payload.passcode, value: this.passcodeValue };
        try {
          const suffix = overwrite ? '?overwrite=1' : '';
          const response = await EduTestApi.request(`/api/v1/quizzes/${this.publicId}${suffix}`, { method: 'PUT', body: JSON.stringify(payload) });
          this.applyServerQuiz(response.data.quiz);
          this.lastSavedAt = new Date();
          this.saveState = 'saved';
          return true;
        } catch (error) {
          if (error.code === 'version_conflict') {
            this.saveState = 'conflict';
            this.conflictVersion = Number(error.fields?.version || 0);
            this.$refs.conflictDialog?.showModal();
          } else if (error.status === 422) {
            this.fieldErrors = error.fields || {};
            this.globalError = error.message;
            this.saveState = 'validation';
          } else {
            this.globalError = error.message;
            this.saveState = 'retry';
          }
          return false;
        } finally {
          this.saving = false;
        }
      },
      async reloadConflict() {
        this.$refs.conflictDialog?.close();
        this.conflictVersion = null;
        await this.load();
      },
      async overwriteConflict() {
        this.$refs.conflictDialog?.close();
        if (this.conflictVersion) this.quiz.version = this.conflictVersion;
        await this.save(true, true);
      },
      beforeUnload(event) {
        if (!['dirty', 'saving', 'retry', 'validation', 'conflict'].includes(this.saveState)) return;
        event.preventDefault();
        event.returnValue = '';
      },
      typeLabel(type) {
        return { single_choice: 'Single choice', multi_select: 'Multi-select', short_text: 'Short text' }[type] || 'Question';
      },
      formatBytes(bytes) {
        if (!Number.isFinite(Number(bytes))) return 'unlimited';
        return `${Math.floor(Number(bytes) / 1048576)} MiB`;
      },
      fieldError(path) { return this.fieldErrors?.[path] || ''; },
      addQuestion(type) {
        let storedType = type;
        let options = [];
        if (type === 'true_false') {
          storedType = 'single_choice';
          options = [{ id: null, content: 'True', isCorrect: true, media: null }, { id: null, content: 'False', isCorrect: false, media: null }];
        } else if (type !== 'short_text') {
          options = [{ id: null, content: '', isCorrect: true, media: null }, { id: null, content: '', isCorrect: false, media: null }];
        }
        this.quiz.questions.push({ id: null, position: this.quiz.questions.length + 1, type: storedType, content: '', explanation: '', points: '1.00', timeLimitSec: null, textAnswers: type === 'short_text' ? [''] : [], media: null, options });
        this.selectedIndex = this.quiz.questions.length - 1;
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
        if (!window.confirm('Changing the answer type will reset its answers and attached answer media. Continue?')) return;
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
      toggleCorrect(index) {
        if (this.selectedQuestion.type === 'single_choice') this.selectedQuestion.options.forEach((option, i) => { option.isCorrect = i === index; });
        else this.selectedQuestion.options[index].isCorrect = !this.selectedQuestion.options[index].isCorrect;
      },
      changeMode(mode) {
        if (mode === this.quiz.mode) return;
        if (mode === 'practice' && !window.confirm('Practice mode clears passcode, identity collection, and integrity settings. Continue?')) return;
        this.quiz.mode = mode;
        if (mode === 'practice') {
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
        if (!await this.save(true)) return null;
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
        input.accept = 'image/jpeg,image/png,image/webp,audio/mpeg,audio/mp4,audio/ogg,audio/wav';
        if (questionIndex === -1) input.accept = 'image/jpeg,image/png,image/webp';
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
        if (questionIndex === -1) this.coverUploading = true;
        try {
          const response = await EduTestApi.request(endpoint, options);
          this.hydrating = true;
          this.quiz.version = response.data.version;
          if (questionIndex === -1) this.quiz.cover = response.data.media;
          else this.targetAt(questionIndex, optionIndex).media = response.data.media;
          this.lastSavedAt = new Date();
          this.saveState = 'saved';
          this.$nextTick(() => { this.hydrating = false; });
        } catch (error) {
          this.globalError = error.message;
          this.saveState = error.code === 'version_conflict' ? 'conflict' : 'retry';
        }
        finally { if (questionIndex === -1) this.coverUploading = false; }
      },
      async lifecycle(action) {
        if (!await this.save(true)) return;
        if (!window.confirm(action === 'publish' ? 'Publish this quiz and activate its stable share page?' : `${action} this quiz?`)) return;
        try {
          const response = await EduTestApi.request(`/api/v1/quizzes/${this.publicId}/${action}`, { method: 'POST', body: '{}' });
          this.applyServerQuiz(response.data.quiz);
          EduTestApi.toast(action === 'publish' ? 'Quiz published.' : 'Quiz updated.');
        } catch (error) {
          this.fieldErrors = error.fields || {};
          this.globalError = error.message;
          this.saveState = error.status === 422 ? 'validation' : 'retry';
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
