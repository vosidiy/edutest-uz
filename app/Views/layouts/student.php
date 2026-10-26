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
            <span id="player-header-separator" aria-hidden="true" hidden>·</span>
            <span id="player-header-progress" hidden></span>
        </div>
        <button id="player-header-quit" type="button" class="btn btn-link danger student-header-quit" hidden><?= esc(lang('Player.ui.quitAction')) ?></button>
    </div>
</header>
<main id="student-main" class="student-main">
    <?= $this->renderSection('content') ?>
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
<?= $this->include('student/partials/player_templates') ?>
<script type="application/json" id="player-config"><?= json_encode([
    'shareToken' => $shareToken, 'page' => $page, 'apiBase' => site_url('api/v1/player'),
    'quizUrl' => site_url('q/' . $shareToken), 'ui' => lang('Player.ui'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script type="module" src="<?= esc(base_url('js/player.js') . '?v=' . filemtime(FCPATH . 'js/player.js'), 'attr') ?>"></script>
</body>
</html>
