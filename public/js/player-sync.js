import {hasPending, mergeServer} from './player-state.js?v=7';

/** Durable, latest-document synchronization for one assessment attempt. */
export class PlayerSync {
  constructor(getState, send, callbacks = {}) {
    this.getState = getState;
    this.send = send;
    this.callbacks = callbacks;
    this.busy = false;
    this.paused = false;
    this.error = null;
    this.timer = null;
    this.task = null;
    this.needsLoad = false;
  }

  schedule(delay = 0) {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.flush(), delay);
  }

  recover() {
    this.needsLoad = true;
    return this.flush();
  }

  flush() {
    if (this.task) return this.task;
    const task = this.drain();
    this.task = task;
    task.finally(() => { if (this.task === task) this.task = null; });
    return task;
  }

  async drain() {
    const state = this.getState();
    if (!state || state.format !== 5 || this.busy || this.paused || this.callbacks.canSend?.() === false
      || (!hasPending(state) && !this.needsLoad)) return;

    this.busy = true;
    this.error = null;
    clearTimeout(this.timer);
    this.callbacks.change?.();
    try {
      while (this.getState() === state && !this.paused && this.callbacks.canSend?.() !== false
        && (hasPending(state) || this.needsLoad)) {
        if (this.needsLoad) {
          this.needsLoad = false;
          const response = await this.send(`/assessments/${state.attemptId}`, {credential: state.credential});
          if (this.getState() !== state) return;
          if (!mergeServer(state, response.data)) {
            this.paused = true;
            this.callbacks.conflict?.(response.data);
            break;
          }
          this.callbacks.persist?.();
          this.callbacks.change?.();
          continue;
        }

        if (state.progressPending && !state.finishPending) {
          const version = state.answerVersion;
          const answers = JSON.parse(JSON.stringify(state.answers));
          const response = await this.send(`/assessments/${state.attemptId}/progress`, {
            method: 'PUT', credential: state.credential, body: {answerVersion: version, answers},
          });
          if (this.getState() !== state) return;
          state.serverVersion = Math.max(state.serverVersion, Number(response.data.answerVersion || version));
          if (state.answerVersion === version) state.progressPending = false;
          this.callbacks.persist?.();
          this.callbacks.change?.();
          continue;
        }

        if (state.finishPending) {
          const response = await this.send(`/assessments/${state.attemptId}/finish`, {
            method: 'POST', credential: state.credential, body: state.finishRecord,
          });
          if (this.getState() !== state) return;
          if (!mergeServer(state, response.data)) {
            this.paused = true;
            this.callbacks.conflict?.(response.data);
            break;
          }
          this.callbacks.persist?.();
          this.callbacks.change?.();
          continue;
        }
        break;
      }
    } catch (error) {
      this.error = error;
      if (error.status === 409) {
        this.paused = true;
        this.callbacks.conflict?.(null);
      } else {
        this.schedule(Math.min(30000, 1000 + Math.floor(Math.random() * 1000)));
      }
    } finally {
      this.busy = false;
      this.callbacks.change?.();
    }
  }

  reset() {
    clearTimeout(this.timer);
    this.needsLoad = false;
    this.paused = false;
    this.error = null;
  }

  stop() {
    this.reset();
  }
}
