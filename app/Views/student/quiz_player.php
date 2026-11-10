<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#4f46e5">
    <title><?= esc($title ?? 'EduTest') ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('css/player.css') . '?v=' . filemtime(FCPATH . 'css/player.css'), 'attr') ?>">
</head>
<body class="student-page">
<header class="student-header">
    <div class="student-header-inner">
        <a class="student-brand" target="_blank" href="<?= site_url('/') ?>" aria-label="123test home">
            <svg aria-hidden="true" width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="36" height="36" rx="12" fill="url(#student-brand-gradient)"></rect>
                <rect x="7" y="7" width="10" height="10" rx="4" fill="#4338ca"></rect>
                <rect x="19" y="7" width="10" height="10" rx="4" fill="#4338ca"></rect>
                <path d="M7 23C7 20.7909 8.79086 19 11 19H13C15.2091 19 17 20.7909 17 23V25C17 27.2091 15.2091 29 13 29H11C8.79086 29 7 27.2091 7 25V23Z" fill="#4338ca"></path>
                <path d="M19 11C19 8.79086 20.7909 7 23 7H25C27.2091 7 29 8.79086 29 11V13C29 15.2091 27.2091 17 25 17H23C20.7909 17 19 15.2091 19 13V11Z" fill="#4338ca"></path>
                <path d="M19 23C19 20.7909 20.7909 19 23 19H25C27.2091 19 29 20.7909 29 23V25C29 27.2091 27.2091 29 25 29H23C20.7909 29 19 27.2091 19 25V23Z" fill="#4338ca"></path>
                <defs>
                    <linearGradient id="student-brand-gradient" x1="18" x2="34" y1="0" y2="36" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#a5b4fc"></stop>
                        <stop offset="1" stop-color="#6163fe"></stop>
                    </linearGradient>
                </defs>
            </svg>
            <span class="student-brand-wordmark">123<em>test</em></span>
        </a>
        <div id="player-header-context" class="student-header-context" hidden>
            <span id="player-header-title"></span>
        </div>
        <div class="player-actions-header">
            <button id="player-header-fullscreen" type="button" class="btn btn-default" aria-pressed="false">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-fullscreen preview-icon"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect width="10" height="8" x="7" y="8" rx="1"/></svg>
                <span>Fullscreen</span>
            </button>
            <button id="player-header-quit" type="button" class="btn btn-default bg-red-100 student-header-quit" hidden><?= esc(lang('Player.ui.quitAction')) ?></button>
        </div>
    </div>
</header>
<main id="student-main" class="student-main">
    <noscript><p><?= esc(lang('Player.ui.missingState')) ?></p><a href="<?= site_url('q/' . $shareToken) ?>"><?= esc(lang('Player.ui.introduction')) ?></a></noscript>
    <div id="player-root" tabindex="-1"></div>
    <div id="player-status" class="player-sync-status" role="status" aria-live="polite"></div>
    <div id="player-alert" class="alert alert-warning" role="alert" hidden></div>
    <div id="player-announcer" class="sr-only" role="status" aria-live="polite"></div>
</main>
<dialog id="player-conflict" class="dialog player-dialog" aria-labelledby="conflict-title">
    <div class="card-body"><h2 id="conflict-title"><?= esc(lang('Player.ui.conflictTitle')) ?></h2>
        <p><?= esc(lang('Player.ui.conflictText')) ?></p>
        <div class="player-actions"><button id="keep-local" type="button" class="btn btn-default"><?= esc(lang('Player.ui.keepLocal')) ?></button>
        <button id="use-server" type="button" class="btn btn-primary"><?= esc(lang('Player.ui.useServer')) ?></button></div>
    </div>
</dialog>

<?php // Inert markup only. player-view.js fills data-slot hooks and binds data-action controls. ?>

<!-- Active question. Template placeholders are replaced without adding layout wrappers. -->
<template id="player-question-template">
    <div class="player-topbar">
        <span id="player-progress-label" class="player-progress-label" data-slot="progress-label"></span>
        <div class="player-timers" data-slot="timers"></div>
    </div>
    <progress class="player-progress" data-slot="progress" aria-labelledby="player-progress-label"></progress>
    <section class="card player-question" aria-labelledby="current-question">
        <h2 id="current-question" data-slot="question"></h2>
        <template data-slot="media"></template>
        <form data-action="answer">
            <fieldset class="player-answers" data-slot="answers">
                <legend data-slot="hint"></legend>
                <template data-slot="options"></template>
            </fieldset>
            <div class="player-actions" data-slot="answer-actions">
                <button type="submit" class="btn btn-primary btn-lg" data-action="submit"></button>
                <button type="button" class="btn btn-default" data-action="skip"></button>
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
        <div style="display:flex; gap:6px">
            <input name="answer" data-action="select">
            <span class="player-option-content">
                <span class="player-option-letter" data-slot="letter"></span>
                <span class="player-option-copy" data-slot="content"></span>
            </span>
        </div>
        <template data-slot="media"></template>
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
        <h3 style="margin-bottom:4px;" data-slot="verdict"></h3>
        <span data-slot="correct-label"></span>
        <template data-slot="correct-answers"></template>
        <p data-slot="explanation-label"></p>
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
        <h2 data-slot="heading"></h2>
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
        <h2 data-slot="title"></h2>
        <h5 style="font-size:18px; color:brown; margin:12px 0" data-slot="message"></h5>
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

<script type="application/json" id="player-config"><?= json_encode([
    'shareToken' => $shareToken, 'page' => $page, 'apiBase' => site_url('api/v1/player'),
    'quizUrl' => site_url('q/' . $shareToken), 'attemptId' => $attemptId ?? null, 'ui' => lang('Player.ui'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script type="module" src="<?= esc(base_url('js/player.js') . '?v=' . filemtime(FCPATH . 'js/player.js'), 'attr') ?>"></script>
</body>
</html>
