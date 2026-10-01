/** Origin-scoped coordination only: no identity, credentials or shared storage. */
export class PlayerTabGuard {
  constructor(shareCode, locks) {
    this.name = 'edutest:quiz-run:' + shareCode;
    try { this.locks = locks === undefined ? globalThis.navigator?.locks : locks; }
    catch (_) { this.locks = null; }
    this.owned = false;
    this.unavailable = false;
    this.fallbackActive = false;
    this.generation = 0;
    this.pending = null;
    this.unlock = null;
  }
  get allowed() { return this.owned || this.fallbackActive; }
  acquire() {
    if (this.allowed) return Promise.resolve(true);
    if (this.pending) return this.pending;
    const generation = this.generation;
    const attempt = new Promise(resolve => {
      const unsupported = () => {
        if (generation !== this.generation) { resolve(false); return; }
        this.unavailable = true;
        this.fallbackActive = true;
        resolve(true);
      };
      if (typeof this.locks?.request !== 'function') { unsupported(); return; }
      try {
        Promise.resolve(this.locks.request(this.name, {mode:'exclusive', ifAvailable:true}, async lock => {
          if (generation !== this.generation || !lock) { resolve(false); return; }
          this.owned = true;
          await new Promise(release => { this.unlock = release; resolve(true); });
          if (generation === this.generation) this.owned = false;
        })).catch(unsupported);
      } catch (_) { unsupported(); }
    });
    this.pending = attempt;
    attempt.finally(() => { if (this.pending === attempt) this.pending = null; });
    return attempt;
  }
  release() {
    this.generation++;
    this.owned = false;
    this.fallbackActive = false;
    this.pending = null;
    this.unlock?.();
    this.unlock = null;
  }
}
