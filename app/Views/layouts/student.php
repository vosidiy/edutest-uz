<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#4f46e5">
    <title><?= esc($title ?? 'EduTest') ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/player.css') ?>">
</head>
<body class="student-page">
<a class="skip-link" href="#student-main"><?= esc(lang('Player.ui.skipContent')) ?></a>
<header class="student-header">
    <a class="student-brand" href="<?= site_url('/') ?>"><span class="brand-mark" aria-hidden="true">E</span>EduTest</a>
    <span class="student-header-label"><?= esc(lang('Player.ui.quizPlayer')) ?></span>
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
<script type="application/json" id="player-config"><?= json_encode([
    'shareToken' => $shareToken, 'page' => $page, 'apiBase' => site_url('api/v1/player'),
    'quizUrl' => site_url('q/' . $shareToken), 'ui' => lang('Player.ui'), 'mode' => $quiz['mode'] ?? null,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script type="module" src="<?= base_url('assets/js/player.js') ?>"></script>
</body>
</html>
