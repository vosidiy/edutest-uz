import {hasPending, mergeServer, syncPayload} from './player-state.js';

/** A single in-flight request stream. UI progression never awaits this worker. */
export class PlayerSync {
  constructor(getState, send, callbacks = {}) {
    this.getState = getState; this.send = send; this.callbacks = callbacks;
    this.busy = false; this.paused = false; this.retryDelay = 1000; this.timer = null;
  }
  schedule(delay = 400) { clearTimeout(this.timer); this.timer = setTimeout(() => this.flush(), delay); }
  async flush() {
    const state = this.getState();
    if (!state || state.mode !== 'assessment' || this.busy || this.paused || !hasPending(state)) return;
    clearTimeout(this.timer);
    this.busy = true;
    this.callbacks.change?.();
    let conflicts = 0;
    try {
      while (hasPending(state) && !this.paused) {
        const payload = syncPayload(state);
        if (payload.items.length || (payload.finishReason && !state.result)) {
          let response;
          try { response = await this.send(`/assessments/${state.attemptId}/sync`, {method: 'POST', body: payload, credential: state.credential}); }
          catch (error) {
            if (error.status !== 409 || ++conflicts > 3) throw error;
            response = await this.send(`/assessments/${state.attemptId}`, {credential: state.credential});
          }
          if (!mergeServer(state, response.data)) {
            this.paused = true;
            this.callbacks.conflict?.(response.data);
            break;
          }
          this.callbacks.persist?.();
          this.callbacks.change?.();
          continue;
        }
        if (state.events.length) {
          const events = state.events.slice(0, 100);
          await this.send(`/assessments/${state.attemptId}/events`, {method: 'POST', body: {events}, credential: state.credential});
          const sentKeys = new Set(events.map(event => event.key));
          state.events = state.events.filter(event => !sentKeys.has(event.key));
          this.callbacks.persist?.();
        }
      }
      this.retryDelay = 1000;
    } catch (error) {
      this.callbacks.error?.(error);
      if ([400, 401, 403, 410, 413, 415, 422].includes(error.status)) this.paused = true;
      else { this.schedule(this.retryDelay); this.retryDelay = Math.min(30000, this.retryDelay * 2); }
    } finally {
      this.busy = false;
      this.callbacks.change?.();
    }
  }
  stop() { clearTimeout(this.timer); this.paused = true; }
}
