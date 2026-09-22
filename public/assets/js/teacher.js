(() => {
  'use strict';

  const csrfHeaderMeta = document.querySelector('meta[name="csrf-header"]');
  const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
  let csrfHeader = csrfHeaderMeta?.content || 'X-CSRF-TOKEN';
  let csrfToken = csrfTokenMeta?.content || '';
  let mutationQueue = Promise.resolve();

  function updateCsrf(meta) {
    if (!meta?.csrfToken) return;
    csrfHeader = meta.csrfHeader || csrfHeader;
    csrfToken = meta.csrfToken;
    if (csrfHeaderMeta) csrfHeaderMeta.content = csrfHeader;
    if (csrfTokenMeta) csrfTokenMeta.content = csrfToken;
  }

  async function refreshCsrf() {
    const response = await fetch('/api/v1/csrf', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) throw new Error('Your session could not be refreshed.');
    const json = await response.json();
    updateCsrf(json.meta);
  }

  async function requestNow(url, options = {}, retry = true) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) headers.set(csrfHeader, csrfToken);
    if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
    const response = await fetch(url, { ...options, method, headers, credentials: 'same-origin' });
    let json = null;
    try { json = await response.json(); } catch (_) { /* CSRF redirects and proxy errors may be HTML. */ }
    updateCsrf(json?.meta);
    if (!response.ok) {
      if (retry && response.status === 403 && !['GET', 'HEAD'].includes(method)) {
        await refreshCsrf();
        return requestNow(url, options, false);
      }
      const error = new Error(json?.error?.message || `Request failed (${response.status}).`);
      error.status = response.status;
      error.code = json?.error?.code || 'request_failed';
      error.fields = json?.error?.fields || {};
      throw error;
    }
    return json;
  }

  function request(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    if (['GET', 'HEAD', 'OPTIONS'].includes(method)) return requestNow(url, options);
    const operation = mutationQueue.then(() => requestNow(url, options));
    mutationQueue = operation.catch(() => undefined);
    return operation;
  }

  function toast(message) {
    const element = document.querySelector('#app-toast');
    if (!element) return;
    element.textContent = message;
    element.classList.add('show');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => element.classList.remove('show'), 2800);
  }

  window.EduTestApi = { request, updateCsrf, toast };

  document.querySelectorAll('[data-date]').forEach(element => {
    if (!element.dataset.date) return;
    const value = new Date(element.dataset.date);
    if (!Number.isNaN(value.getTime())) element.textContent = `Updated ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(value)}`;
  });

  document.querySelectorAll('[data-href]').forEach(row => {
    const open = () => { window.location.href = row.dataset.href; };
    row.addEventListener('click', open);
    row.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') open(); });
  });

  const createDialog = document.querySelector('#create-quiz-dialog');
  document.querySelectorAll('[data-open-create]').forEach(button => button.addEventListener('click', () => {
    createDialog?.showModal();
    setTimeout(() => createDialog?.querySelector('input[name="title"]')?.focus(), 0);
  }));

  const createForm = createDialog?.querySelector('[data-create-form]');
  createForm?.addEventListener('submit', async event => {
    const submitter = event.submitter;
    if (submitter?.value === 'cancel') return;
    event.preventDefault();
    const error = createForm.querySelector('[data-create-error]');
    const button = createForm.querySelector('button[type="submit"]');
    error.textContent = '';
    button.disabled = true;
    try {
      const data = new FormData(createForm);
      const result = await request('/api/v1/quizzes', { method: 'POST', body: JSON.stringify({ title: data.get('title'), mode: data.get('mode') }) });
      window.location.href = result.data.editUrl;
    } catch (failure) {
      error.textContent = failure.fields?.title || failure.fields?.mode || failure.message;
    } finally {
      button.disabled = false;
    }
  });

  document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-menu-toggle]');
    document.querySelectorAll('.action-menu .menu').forEach(menu => {
      if (!toggle || menu !== toggle.nextElementSibling) menu.hidden = true;
    });
    if (toggle) toggle.nextElementSibling.hidden = !toggle.nextElementSibling.hidden;
  });

  document.querySelectorAll('[data-quiz-action]').forEach(button => button.addEventListener('click', async () => {
    const row = button.closest('[data-quiz-id]');
    const action = button.dataset.quizAction;
    const publicId = row?.dataset.quizId;
    if (!publicId) return;
    const confirmation = {
      publish: 'Publish this quiz and activate its share page?',
      close: 'Close this quiz to new starts?',
      reopen: 'Reopen this quiz?',
      archive: 'Archive this quiz?',
      unarchive: 'Restore this quiz from the archive?',
      trash: 'Move this quiz to trash?',
      restore: 'Restore this quiz from trash?',
      duplicate: 'Create an independent draft copy of this quiz?'
    }[action];
    if (confirmation && !window.confirm(confirmation)) return;
    button.disabled = true;
    try {
      const result = await request(`/api/v1/quizzes/${publicId}/${action}`, { method: 'POST', body: '{}' });
      if (action === 'duplicate') window.location.href = result.data.editUrl;
      else window.location.reload();
    } catch (failure) {
      toast(failure.message);
      button.disabled = false;
    }
  }));
})();
