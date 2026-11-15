(function (global) {
  'use strict';

  const player = global.EduTestPlayer = global.EduTestPlayer || {};
  const escapeCharacters = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
  const columnsByLayout = {list: 1, grid: 2, 'grid-3': 3, 'grid-4': 4};

  // Escape authored text before inserting template literals into the DOM.
  function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => escapeCharacters[character]);
  }

  function markup(html) {
    const container = document.createElement('div');
    const fragment = document.createDocumentFragment();
    container.innerHTML = html.trim();
    while (container.firstChild) fragment.append(container.firstChild);
    return fragment;
  }

  function find(root, selector) {
    const element = root.querySelector(selector);
    if (!element) throw new Error(`Missing player element: ${selector}`);
    return element;
  }

  function replacePlaceholder(root, selector, content) {
    const placeholder = find(root, selector);
    if (content) placeholder.replaceWith(content);
    else placeholder.remove();
  }

  // Return DOM builders; player.js owns state and passes translated text and event callbacks.
  function createPlayerView(t) {
    function timer(deadline) {
      const fragment = markup(`
        <div class="player-timer" aria-live="off" data-deadline="">
          <span class="player-timer-label">${escapeHTML(t(deadline.label))}</span>
          <strong class="player-timer-remaining"></strong>
        </div>
      `);
      fragment.firstElementChild.dataset.deadline = String(deadline.at);
      return fragment;
    }

    function choice(option, selected, onChange, media) {
      const fragment = markup(`
        <label class="player-option${selected ? ' is-selected' : ''}">
          <div style="display:flex; gap:6px">
            <input class="player-option-input" name="answer" data-action="select">
            <span class="player-option-content">
              <span class="player-option-letter">${escapeHTML(`${option.letter})`)}</span>
              <span class="player-option-copy">${escapeHTML(option.content)}</span>
            </span>
          </div>
          <span class="player-option-media-placeholder" hidden></span>
        </label>
      `);
      const label = fragment.firstElementChild;
      const input = find(fragment, '.player-option-input');
      input.type = 'radio';
      input.value = option.code;
      input.checked = selected;
      input.ariaLabel = `${option.letter}) ${option.content || t('mediaImage')}`;
      input.addEventListener('change', onChange);
      replacePlaceholder(fragment, '.player-option-media-placeholder', media());
      return label;
    }

    function question(model, callbacks) {
      const progressLabel = t('questionOf', {number: model.number, total: model.total});
      const answerColumns = columnsByLayout[model.question.optionsLayout] || 1;
      const fragment = markup(`
        <div class="player-topbar">
          <span id="player-progress-label" class="player-progress-label">${escapeHTML(progressLabel)}</span>
          <div class="player-timers"></div>
        </div>
        <progress class="player-progress" aria-labelledby="player-progress-label"></progress>
        <section class="card player-question" aria-labelledby="current-question">
          <h2 id="current-question">${escapeHTML(model.question.content)}</h2>
          <span class="player-question-media-placeholder" hidden></span>
          <form class="player-answer-form" data-action="answer">
            <fieldset class="player-answers player-answers-columns-${answerColumns}">
              <legend>${escapeHTML(t(model.hint))}</legend>
              <span class="player-options-placeholder" hidden></span>
            </fieldset>
            <div class="player-actions player-answer-actions">
              <button type="submit" class="btn btn-primary btn-lg player-submit" data-action="submit">${escapeHTML(t('submit'))}</button>
              <button type="button" class="btn btn-default player-skip" data-action="skip">${escapeHTML(t('skip'))}</button>
            </div>
          </form>
          <span class="player-feedback-placeholder" hidden></span>
          <div class="player-actions player-next-actions">
            <button type="button" class="btn btn-primary btn-lg player-next" data-action="next">
              <span class="player-next-label">${escapeHTML(t(model.last ? 'finish' : 'next'))}</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right preview-icon" aria-hidden="true" focusable="false"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </button>
          </div>
        </section>
      `);

      const timers = find(fragment, '.player-timers');
      for (const deadline of model.deadlines) timers.append(timer(deadline));

      const progress = find(fragment, '.player-progress');
      progress.max = model.total;
      progress.value = model.confirmedCount;
      progress.ariaValueText = progressLabel;
      replacePlaceholder(fragment, '.player-question-media-placeholder', callbacks.media(`q:${model.question.id}`, model.question.media));

      const answers = find(fragment, '.player-answers');
      const submit = find(fragment, '.player-submit');
      answers.disabled = model.locked;
      submit.disabled = !callbacks.valid();
      const options = document.createDocumentFragment();
      const onChange = () => {
        const selected = answers.querySelector('.player-option-input:checked')?.value ?? null;
        if (!callbacks.onEdit(selected)) return;
        answers.querySelectorAll('.player-option').forEach(label => {
          label.classList.toggle('is-selected', find(label, '.player-option-input').checked);
        });
        submit.disabled = !callbacks.valid();
      };
      for (const option of model.options) {
        options.append(choice(option, model.item.answerCodes.includes(option.code), onChange,
          () => callbacks.media(`o:${option.id}`, option.media)));
      }
      replacePlaceholder(fragment, '.player-options-placeholder', options);
      find(fragment, '.player-answer-form').addEventListener('submit', event => {
        event.preventDefault();
        if (callbacks.valid()) callbacks.onSubmit();
      });

      if (model.locked) {
        find(fragment, '.player-answer-actions').remove();
        replacePlaceholder(fragment, '.player-feedback-placeholder', callbacks.feedback());
        find(fragment, '.player-next').addEventListener('click', callbacks.onNext);
      } else {
        find(fragment, '.player-skip').addEventListener('click', callbacks.onSkip);
        find(fragment, '.player-next-actions').remove();
        replacePlaceholder(fragment, '.player-feedback-placeholder', null);
      }
      return fragment;
    }

    function feedback(model) {
      const headingKey = model.verdict === 'correct'
        ? 'feedbackCorrect'
        : model.verdict === 'wrong' ? 'feedbackWrong' : 'unanswered';
      const correctPrefix = model.verdict === 'correct' ? '' : `${t(model.correctLabel)}: `;
      const correctAnswers = model.showAnswers
        ? model.correctAnswers.map(answer => `<p class="player-feedback-answer">${escapeHTML(correctPrefix + answer.text)}</p>`).join('')
        : '';
      const explanation = model.explanation ? `
        <p class="player-feedback-explanation-label"><strong>${escapeHTML(t('explanation'))}:</strong></p>
        <p class="player-feedback-explanation">${escapeHTML(model.explanation)}</p>
      ` : '';
      return markup(`
        <section class="player-feedback ${escapeHTML(model.verdict)}">
          <h3>${escapeHTML(t(headingKey))}</h3>
          ${correctAnswers}
          ${explanation}
        </section>
      `);
    }

    function resultStat(name, count) {
      const labels = {correct: 'resultCorrect', wrong: 'resultWrong', unanswered: 'resultUnanswered', total: 'resultTotal'};
      return markup(`
        <div class="player-result-stat ${escapeHTML(name)}">
          <span class="player-result-stat-icon" aria-hidden="true"></span>
          <span class="player-result-stat-copy">
            <span class="player-result-stat-label">${escapeHTML(t(labels[name]))}</span>
            <strong>${escapeHTML(count)}</strong>
          </span>
        </div>
      `);
    }

    function results(model, onRestart) {
      const identity = model.identity
        ? `<p class="player-student-identity">${escapeHTML(model.identity)}</p>` : '';
      const status = model.status
        ? `<p class="player-help player-result-status">${escapeHTML(t(model.status))}</p>` : '';
      const warning = model.scoreChanged
        ? `<p class="alert alert-warning player-result-warning">${escapeHTML(t('scoreChanged'))}</p>` : '';
      const reason = model.reason
        ? `<p class="player-result-reason">${escapeHTML(t(model.reason))}</p>` : '';
      const actions = model.restart ? `
        <div class="player-actions player-result-actions">
          <button type="button" class="btn btn-primary btn-lg player-restart" data-action="restart">${escapeHTML(t(model.restart))}</button>
        </div>
      ` : '';
      const fragment = markup(`
        <section class="card player-results-header">
          <span class="player-completion-icon" aria-hidden="true">${escapeHTML(model.emoji)}</span>
          <h2 class="player-result-heading">${escapeHTML(t(model.heading))}</h2>
          <p class="player-result-title">${escapeHTML(model.title)}</p>
          ${identity}
          <div class="player-score">${escapeHTML(`${model.percent}%`)}</div>
          <div class="player-result-stats"></div>
          ${status}
          ${warning}
          ${reason}
          ${actions}
        </section>
      `);
      const stats = find(fragment, '.player-result-stats');
      for (const name of ['correct', 'wrong', 'unanswered']) stats.append(resultStat(name, model.counts[name]));
      stats.append(resultStat('total', model.total));
      if (model.restart) {
        const restart = find(fragment, '.player-restart');
        restart.disabled = model.starting;
        if (model.restart === 'tryAgain') restart.dataset.tryAgain = '';
        restart.addEventListener('click', onRestart);
      }
      return fragment;
    }

    function review(items, onExpand) {
      const fragment = markup(`
        <section class="player-review">
          <h2>${escapeHTML(t('review'))}</h2>
          <div class="player-review-items"></div>
        </section>
      `);
      const rows = find(fragment, '.player-review-items');
      for (const [index, item] of items.entries()) {
        const row = markup(`
          <details class="card player-review-item">
            <summary>
              <span class="player-question-number">${escapeHTML(index + 1)}</span>
              <strong>${escapeHTML(item.question)}</strong>
              <span class="badge badge-${escapeHTML(item.verdict)} player-review-verdict">${escapeHTML(t(item.verdict))}</span>
            </summary>
          </details>
        `);
        const details = row.firstElementChild;
        details.addEventListener('toggle', () => {
          if (!details.open || details.dataset.loaded) return;
          details.dataset.loaded = 'true';
          onExpand(details, index);
        });
        rows.append(row);
      }
      return fragment;
    }

    function media(kind, key, url, onError) {
      const wrapper = markup('<div class="player-media"></div>').firstElementChild;
      wrapper.dataset.mediaKey = key;
      const element = markup(kind === 'image' ? '<img>' : '<audio controls preload="metadata"></audio>').firstElementChild;
      if (kind === 'image') element.alt = t('mediaImage');
      element.addEventListener('error', () => onError(wrapper));
      element.src = url;
      wrapper.append(element);
      return wrapper;
    }

    function mediaError(wrapper, onRetry) {
      if (wrapper.querySelector('.player-media-retry')) return;
      const fragment = markup(`
        <p class="player-help player-media-error">${escapeHTML(t('mediaError'))}</p>
        <button type="button" class="btn btn-default btn-sm player-media-retry" data-action="retry-media">${escapeHTML(t('retry'))}</button>
      `);
      find(fragment, '.player-media-retry').addEventListener('click', onRetry);
      wrapper.append(fragment);
    }

    function status(label, onRetry, actionLabel = 'retry') {
      const retry = onRetry
        ? `<button type="button" class="btn btn-default btn-sm player-status-retry" data-action="retry">${escapeHTML(t(actionLabel))}</button>`
        : '';
      const fragment = markup(`
        <span class="player-status-label">${escapeHTML(label)}</span>
        ${retry}
      `);
      if (onRetry) find(fragment, '.player-status-retry').addEventListener('click', onRetry);
      return fragment;
    }

    function blocked(title, onRetry) {
      const fragment = markup(`
        <section class="card player-error" data-tab-blocked role="alert">
          <h2>${escapeHTML(title)}</h2>
          <h5 style="font-size:18px; color:brown; margin:12px 0">${escapeHTML(t('tabBlocked'))}</h5>
          <button type="button" class="btn btn-primary player-tab-retry" data-action="retry">${escapeHTML(t('retry'))}</button>
        </section>
      `);
      find(fragment, '.player-tab-retry').addEventListener('click', onRetry);
      return fragment;
    }

    return {question, feedback, results, review, media, mediaError, status, blocked};
  }

  Object.assign(player, {createPlayerView, escapeHTML});
})(globalThis);
