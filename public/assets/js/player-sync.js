import {hasPending, mergeServer, acknowledge} from './player-state.js?v=5';

/** One coordinator for recovery, confirmations, Finish, activity and integrity. */
export class PlayerSync {
  constructor(getState, send, callbacks = {}) {
    this.getState = getState; this.send = send; this.callbacks = callbacks;
    this.busy = false; this.paused = false; this.retryDelay = 1000; this.timer = null; this.needsLoad = false;
  }
  schedule(delay = 0) { clearTimeout(this.timer); this.timer = setTimeout(() => this.flush(), delay); }
  recover() { this.needsLoad = true; return this.flush(); }
  async flush() {
    const state = this.getState();
    if (!state || state.format !== 4 || state.mode !== 'assessment' || this.busy || this.paused || this.callbacks.canSend?.() === false || (!hasPending(state) && !this.needsLoad)) return;
    clearTimeout(this.timer); this.busy = true; this.callbacks.change?.();
    try {
      while ((hasPending(state) || this.needsLoad) && !this.paused && this.callbacks.canSend?.() !== false && this.getState() === state) {
        if (this.needsLoad) {
          this.needsLoad = false;
          try {
            const response = await this.send('/assessments/' + state.attemptId, {credential:state.credential});
            if (!mergeServer(state, response.data)) {
              this.paused = true; this.callbacks.conflict?.(response.data); break;
            }
            this.callbacks.persist?.(); this.callbacks.change?.();
          } catch (error) { this.needsLoad = true; throw error; }
          continue;
        }
        if (state.pendingAnswers.length) {
          const operation = state.pendingAnswers[0];
          const {questionId, ...body} = operation;
          const response = await this.send('/assessments/' + state.attemptId + '/answers/' + questionId, {method:'PUT', credential:state.credential, body});
          acknowledge(state, response.data, operation);
          this.callbacks.persist?.(); this.callbacks.change?.(); continue;
        }
        if (state.finishPending && !state.result?.confirmed) {
          const response = await this.send('/assessments/' + state.attemptId + '/finish', {method:'POST', body:state.finishRecord, credential:state.credential});
          acknowledge(state, response.data);
          this.callbacks.persist?.(); this.callbacks.change?.(); continue;
        }
        // Activity precedes events so offline inactivity recovery supplies their valid duration.
        if (state.activityPending && Date.now() - state.activitySentAt >= 60000) {
          const activity = state.clientActivityAt;
          state.activitySentAt = Date.now(); this.callbacks.persist?.();
          const response = await this.send('/assessments/' + state.attemptId + '/activity', {method:'POST', body:{clientActivityAt:activity}, credential:state.credential});
          acknowledge(state, response.data);
          this.callbacks.persist?.(); this.callbacks.change?.(); continue;
        }
        if (state.events.length && !state.activityPending) {
          const events = state.events.slice(0, 100);
          await this.send('/assessments/' + state.attemptId + '/events', {method:'POST', body:{events}, credential:state.credential});
          const keys = new Set(events.map(event => event.key));
          state.events = state.events.filter(event => !keys.has(event.key)); this.callbacks.persist?.(); continue;
        }
        if (state.activityPending) this.schedule(Math.max(1, 60000 - (Date.now() - state.activitySentAt)));
        break;
      }
      this.retryDelay = 1000;
    } catch (error) {
      this.callbacks.error?.(error);
      if (['answer_locked','finish_conflict','attempt_finalized'].includes(error.code)) {
        this.paused = true;
        // Full state is loaded only for an actual conflict, never after a normal acknowledgement.
        try {
          const saved = await this.send('/assessments/' + state.attemptId, {credential:state.credential});
          this.callbacks.conflict?.(saved.data);
        } catch (_) { /* Keep the local queue and original error. */ }
      } else if ([400,401,403,410,413,415,422].includes(error.status)) this.paused = true;
      else if (this.callbacks.canSend?.() !== false) {
        if (error.code === 'incomplete_sync') this.needsLoad = true;
        this.schedule(this.retryDelay); this.retryDelay = Math.min(30000, this.retryDelay * 2);
      }
    } finally { this.busy = false; this.callbacks.change?.(); }
  }
  retry() { this.paused = false; this.schedule(); }
  stop() { clearTimeout(this.timer); this.paused = true; }
}
