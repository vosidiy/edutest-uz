<?php // Inert markup only. player-view.js fills data-slot hooks and binds data-action controls. ?>

<!-- Active question. Template placeholders are replaced without adding layout wrappers. -->
<template id="player-question-template">
    <div class="player-topbar">
        <div class="player-current-user">
            <p class="player-quiz-title" data-slot="title"></p>
            <small data-slot="identity"></small>
        </div>
        <div class="player-timers" data-slot="timers"></div>
    </div>
    <progress class="player-progress" data-slot="progress"></progress>
    <section class="card player-question" aria-labelledby="current-question">
        <h2 id="current-question" tabindex="-1" data-slot="question"></h2>
        <template data-slot="media"></template>
        <form data-action="answer">
            <fieldset class="player-answers" data-slot="answers">
                <legend data-slot="hint"></legend>
                <template data-slot="options"></template>
            </fieldset>
            <div class="player-actions" data-slot="answer-actions">
                <button type="button" class="btn btn-default" data-action="skip"></button>
                <button type="submit" class="btn btn-primary btn-lg" data-action="submit"></button>
            </div>
        </form>
        <template data-slot="feedback"></template>
        <div class="player-actions" data-slot="next-actions">
            <button type="button" class="btn btn-primary btn-lg" data-action="next">
                <span data-slot="next-label"></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right preview-icon" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
        </div>
    </section>
</template>

<template id="player-choice-template">
    <label class="player-option" data-slot="choice">
        <input name="answer" data-action="select">
        <span class="player-option-content">
            <span class="player-option-letter" data-slot="letter"></span>
            <span class="player-option-copy" data-slot="content"></span>
            <template data-slot="media"></template>
        </span>
    </label>
</template>

<template id="player-timer-template">
    <div class="player-timer" aria-live="off">
        <span data-slot="label"></span>
        <strong data-slot="remaining"></strong>
    </div>
</template>

<!-- Shared immediate feedback and expanded result review. -->
<template id="player-feedback-template">
    <section class="player-feedback">
        <h3 data-slot="verdict"></h3>
        <p data-slot="answer"></p>
        <strong style="margin-top:8px; display:block;" data-slot="correct-label"></strong>
        <template data-slot="correct-answers"></template>
        <strong data-slot="explanation-label"></strong>
        <p data-slot="explanation"></p>
    </section>
</template>

<template id="player-feedback-answer-template">
    <p data-slot="text"></p>
    <template data-slot="media"></template>
</template>

<!-- Completion and review. Keep score-dependent content removable for hidden scores. -->
<template id="player-results-template">
    <section class="card player-results-header">
        <span class="player-completion-icon" aria-hidden="true" data-slot="emoji"></span>
        <h2 tabindex="-1" data-slot="heading"></h2>
        <p class="player-result-title" data-slot="title"></p>
        <p class="player-student-identity" data-slot="identity"></p>
        <div class="player-score" data-slot="score"></div>
        <div class="player-result-stats" data-slot="stats"></div>
        <p class="player-hidden-score" data-slot="hidden-score"></p>
        <p class="player-help" data-slot="status"></p>
        <p class="alert alert-warning" data-slot="warning"></p>
        <p data-slot="reason"></p>
        <div class="player-actions" data-slot="actions">
            <button type="button" class="btn btn-primary btn-lg" data-action="restart"></button>
        </div>
    </section>
</template>

<template id="player-result-stat-template">
    <div class="player-result-stat">
        <span class="player-result-stat-icon" aria-hidden="true"></span>
        <span class="player-result-stat-copy">
            <span class="player-result-stat-label" data-slot="label"></span>
            <strong data-slot="count"></strong>
        </span>
    </div>
</template>

<template id="player-review-template">
    <section class="player-review">
        <h2 data-slot="heading"></h2>
        <template data-slot="items"></template>
    </section>
</template>

<template id="player-review-item-template">
    <details class="card player-review-item">
        <summary>
            <span class="player-question-number" data-slot="number"></span>
            <strong data-slot="question"></strong>
            <span class="badge" data-slot="verdict"></span>
        </summary>
    </details>
</template>

<!-- Media URLs are assigned only when these templates are cloned. -->
<template id="player-media-template">
    <div class="player-media"><template data-slot="content"></template></div>
</template>
<template id="player-image-template">
    <img>
</template>
<template id="player-audio-template">
    <audio controls preload="metadata"></audio>
</template>
<template id="player-media-error-template">
    <p class="player-help" data-slot="message"></p>
    <button type="button" class="btn btn-default btn-sm" data-action="retry-media"></button>
</template>

<!-- Synchronization, blocked tabs, and recovery. -->
<template id="player-status-template">
    <span data-slot="label"></span>
    <button type="button" class="btn btn-default btn-sm" data-action="retry"></button>
</template>
<template id="player-blocked-template">
    <section class="card player-error" data-tab-blocked role="alert">
        <h1 data-slot="title"></h1>
        <p data-slot="message"></p>
        <button type="button" class="btn btn-primary" data-action="retry"></button>
    </section>
</template>
<template id="player-legacy-template">
    <section class="card player-error">
        <p data-slot="message"></p>
        <details data-slot="disclosure">
            <summary data-slot="label"></summary>
            <pre class="player-local-backup" data-slot="backup"></pre>
        </details>
    </section>
</template>
<template id="player-missing-template">
    <p class="card player-error" data-slot="message"></p>
    <a class="btn btn-primary" data-action="introduction"></a>
</template>
