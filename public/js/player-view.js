// Markup lives in app/Views/student/partials/player_templates.php.
// This module fills trusted template structure with plain text and binds callbacks.
function cloneTemplate(name) {
  const template = document.getElementById(`player-${name}-template`);
  if (!(template instanceof HTMLTemplateElement)) throw new Error(`Missing player template: ${name}`);
  return template.content.cloneNode(true);
}

const slot = (root, name) => root.querySelector(`[data-slot="${name}"]`);
const action = (root, name) => root.querySelector(`[data-action="${name}"]`);
const text = (root, name, value) => { slot(root, name).textContent = value; };
const insert = (root, name, content) => { slot(root, name).replaceWith(...(content ? [content] : [])); };
function optionalText(root, name, value) {
  if (value) text(root, name, value);
  else slot(root, name).remove();
}

export function createPlayerView(t) {
  function fullscreen(active, onEnable) {
    const fragment = cloneTemplate('fullscreen');
    const control = action(fragment, 'fullscreen');
    control.textContent = t('enableFullscreen');
    control.hidden = active;
    control.addEventListener('click', onEnable);
    text(fragment, 'status', t(active ? 'fullscreenActive' : 'fullscreenRecommended'));
    return fragment;
  }

  function timer(deadline) {
    const fragment = cloneTemplate('timer');
    fragment.firstElementChild.dataset.deadline = String(deadline.at);
    text(fragment, 'label', t(deadline.label));
    return fragment;
  }

  function choice(option, type, selected, onChange, media) {
    const fragment = cloneTemplate('choice');
    const label = slot(fragment, 'choice');
    const input = action(fragment, 'select');
    label.classList.toggle('is-selected', selected);
    input.type = type === 'single_choice' ? 'radio' : 'checkbox';
    input.value = option.code;
    input.checked = selected;
    input.setAttribute('aria-label', `${option.letter}) ${option.content || t('mediaImage')}`);
    input.addEventListener('change', onChange);
    text(fragment, 'letter', `${option.letter})`);
    text(fragment, 'content', option.content);
    insert(fragment, 'media', media());
    return fragment;
  }

  function question(model, callbacks) {
    const fragment = cloneTemplate('question');
    text(fragment, 'title', model.title);
    optionalText(fragment, 'identity', model.identity);
    const timers = slot(fragment, 'timers');
    for (const deadline of model.deadlines) timers.append(timer(deadline));
    if (model.monitored) timers.append(fullscreen(model.fullscreen, callbacks.onFullscreen));
    const progress = slot(fragment, 'progress');
    progress.max = model.total;
    progress.value = model.confirmedCount;
    progress.setAttribute('aria-label', t('progress'));
    text(fragment, 'question', model.question.content);
    insert(fragment, 'media', callbacks.media(`q:${model.question.id}`, model.question.media));

    const answers = slot(fragment, 'answers');
    const submit = action(fragment, 'submit');
    answers.disabled = model.locked;
    text(fragment, 'hint', t(model.hint));
    submit.textContent = t('submit');
    submit.disabled = !callbacks.valid();
    const options = document.createDocumentFragment();
    if (model.question.type === 'short_text') {
      const field = cloneTemplate('text-answer');
      const input = action(field, 'type');
      input.value = model.item.textAnswer;
      input.setAttribute('aria-label', t('textHint'));
      input.setAttribute('placeholder', 'Type here');
      input.setAttribute('autofocus', true);
      input.addEventListener('input', () => {
        if (!callbacks.onEdit({textAnswer: input.value})) return;
        submit.disabled = !callbacks.valid();
      });
      options.append(field);
    } else {
      const onChange = () => {
        const selected = [...answers.querySelectorAll('[data-action="select"]:checked')].map(input => input.value);
        if (!callbacks.onEdit({answerCodes: selected})) return;
        answers.querySelectorAll('[data-slot="choice"]').forEach(label => {
          label.classList.toggle('is-selected', action(label, 'select').checked);
        });
        submit.disabled = !callbacks.valid();
      };
      for (const option of model.options) {
        options.append(choice(option, model.question.type, model.item.answerCodes.includes(option.code), onChange,
          () => callbacks.media(`o:${option.id}`, option.media)));
      }
    }
    insert(fragment, 'options', options);
    action(fragment, 'answer').addEventListener('submit', event => {
      event.preventDefault();
      if (callbacks.valid()) callbacks.onSubmit();
    });
    if (model.locked) {
      slot(fragment, 'answer-actions').remove();
      insert(fragment, 'feedback', callbacks.feedback());
      const next = action(fragment, 'next');
      text(fragment, 'next-label', t(model.last ? 'finish' : 'next'));
      next.addEventListener('click', callbacks.onNext);
    } else {
      const skip = action(fragment, 'skip');
      skip.textContent = t('skip');
      skip.addEventListener('click', callbacks.onSkip);
      slot(fragment, 'next-actions').remove();
      insert(fragment, 'feedback', null);
    }
    return fragment;
  }

  function feedback(model, media) {
    const fragment = cloneTemplate('feedback');
    fragment.firstElementChild.classList.add(model.verdict);
    text(fragment, 'verdict', t(model.verdict));
    text(fragment, 'answer', `${t('yourAnswer')}: ${model.answer}`);
    optionalText(fragment, 'correct-label', model.showAnswers ? t(model.correctLabel) : '');
    const answers = document.createDocumentFragment();
    for (const answer of model.correctAnswers) {
      const line = cloneTemplate('feedback-answer');
      text(line, 'text', answer.text);
      insert(line, 'media', answer.media ? media(answer.key, answer.media) : null);
      answers.append(line);
    }
    insert(fragment, 'correct-answers', answers);
    optionalText(fragment, 'explanation-label', model.explanation ? t('explanation') : '');
    optionalText(fragment, 'explanation', model.explanation);
    return fragment;
  }

  function resultStat(name, count, total) {
    const fragment = cloneTemplate('result-stat');
    fragment.firstElementChild.classList.add(name);
    text(fragment, 'label', t({correct: 'resultCorrect', wrong: 'resultWrong', unanswered: 'resultUnanswered'}[name]));
    text(fragment, 'count', `${count}/${total}`);
    return fragment;
  }

  function results(model, onRestart) {
    const fragment = cloneTemplate('results');
    text(fragment, 'emoji', model.emoji);
    text(fragment, 'heading', t(model.heading));
    text(fragment, 'title', model.title);
    optionalText(fragment, 'identity', model.identity);
    if (model.scoreVisible) {
      text(fragment, 'score', `${model.percent}%`);
      const stats = slot(fragment, 'stats');
      for (const name of ['correct', 'wrong', 'unanswered']) stats.append(resultStat(name, model.counts[name], model.total));
      slot(fragment, 'hidden-score').remove();
    } else {
      slot(fragment, 'score').remove();
      slot(fragment, 'stats').remove();
      text(fragment, 'hidden-score', t('hiddenScore'));
    }
    optionalText(fragment, 'status', model.status ? t(model.status) : '');
    optionalText(fragment, 'warning', model.scoreChanged ? t('scoreChanged') : '');
    optionalText(fragment, 'reason', model.reason ? t(model.reason) : '');
    if (model.restart) {
      const restart = action(fragment, 'restart');
      restart.textContent = t(model.restart);
      restart.disabled = model.starting;
      if (model.restart === 'tryAgain') restart.dataset.tryAgain = '';
      restart.addEventListener('click', onRestart);
    } else slot(fragment, 'actions').remove();
    return fragment;
  }

  function review(items, onExpand) {
    const fragment = cloneTemplate('review');
    text(fragment, 'heading', t('review'));
    const rows = document.createDocumentFragment();
    for (const [index, item] of items.entries()) {
      const row = cloneTemplate('review-item');
      const details = row.firstElementChild;
      text(row, 'number', String(index + 1));
      text(row, 'question', item.question);
      text(row, 'verdict', t(item.verdict));
      details.addEventListener('toggle', () => {
        if (!details.open || details.dataset.loaded) return;
        details.dataset.loaded = 'true';
        onExpand(details, index);
      });
      rows.append(row);
    }
    insert(fragment, 'items', rows);
    return fragment;
  }

  function media(kind, key, url, onError) {
    const fragment = cloneTemplate('media');
    const wrapper = fragment.firstElementChild;
    wrapper.dataset.mediaKey = key;
    const content = cloneTemplate(kind);
    const element = content.firstElementChild;
    if (kind === 'image') element.alt = t('mediaImage');
    if (kind === 'embed') element.title = t('mediaVideo');
    element.addEventListener('error', () => onError(wrapper));
    element.src = url;
    insert(fragment, 'content', content);
    return wrapper;
  }

  function mediaError(wrapper, onRetry) {
    if (action(wrapper, 'retry-media')) return;
    const fragment = cloneTemplate('media-error');
    text(fragment, 'message', t('mediaError'));
    const retry = action(fragment, 'retry-media');
    retry.textContent = t('retry');
    retry.addEventListener('click', onRetry);
    wrapper.append(fragment);
  }

  function status(label, onRetry, actionLabel = 'retry') {
    const fragment = cloneTemplate('status');
    text(fragment, 'label', label);
    const retry = action(fragment, 'retry');
    if (onRetry) {
      retry.textContent = t(actionLabel);
      retry.addEventListener('click', onRetry);
    } else retry.remove();
    return fragment;
  }

  function blocked(title, onRetry) {
    const fragment = cloneTemplate('blocked');
    text(fragment, 'title', title);
    text(fragment, 'message', t('tabBlocked'));
    const retry = action(fragment, 'retry');
    retry.textContent = t('retry');
    retry.addEventListener('click', onRetry);
    return fragment;
  }

  function legacy(backup) {
    const fragment = cloneTemplate('legacy');
    text(fragment, 'message', t('legacyProgress'));
    if (backup !== null) {
      text(fragment, 'label', t('localProgress'));
      text(fragment, 'backup', backup);
    } else slot(fragment, 'disclosure').remove();
    return fragment;
  }

  function missing(url) {
    const fragment = cloneTemplate('missing');
    text(fragment, 'message', t('missingState'));
    const link = action(fragment, 'introduction');
    link.textContent = t('introduction');
    link.href = url;
    return fragment;
  }

  return {question, feedback, results, review, media, mediaError, status, blocked, legacy, missing};
}
