<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-header" content="<?= esc(csrf_header(), 'attr') ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <meta name="theme-color" content="#0f172a">
    <title><?= esc($title ?? 'EduTest') ?></title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/css/teacher.css">
    <?= $this->renderSection('head') ?>
</head>
<body class="<?= esc($bodyClass ?? '', 'attr') ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="teacher-shell">
    <aside class="teacher-sidebar" aria-label="Teacher navigation">
        <a class="teacher-brand" href="<?= site_url('dashboard') ?>" aria-label="EduTest overview">
            <span class="brand-mark">E</span><span>EduTest</span>
        </a>
        <nav class="teacher-nav">
            <a class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= site_url('dashboard') ?>">
                <span aria-hidden="true">▦</span> Overview
            </a>
            <a class="nav-link <?= ($activeNav ?? '') === 'quizzes' ? 'active' : '' ?>" href="<?= site_url('quizzes') ?>">
                <span aria-hidden="true">▤</span> My quizzes
            </a>
            <span class="nav-link disabled" aria-disabled="true" title="Results arrive with the assessment milestone">
                <span aria-hidden="true">▥</span> Results <small>Later</small>
            </span>
        </nav>
        <div class="sidebar-profile">
            <?php
            $displayName = (string) ($user['display_name'] ?? 'Teacher');
            $parts = preg_split('/\s+/u', trim($displayName)) ?: [];
            $initials = '';
            foreach (array_slice($parts, 0, 2) as $part) {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            }
            ?>
            <span class="avatar"><?= esc($initials ?: 'T') ?></span>
            <span class="profile-copy"><strong><?= esc($displayName) ?></strong><small>Teacher workspace</small></span>
            <form action="<?= site_url('logout') ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-icon sidebar-logout" type="submit" title="Sign out" aria-label="Sign out">↪</button>
            </form>
        </div>
    </aside>
    <main class="teacher-main" id="main-content">
        <?= $this->renderSection('content') ?>
    </main>
</div>
<div class="app-toast" id="app-toast" role="status" aria-live="polite"></div>
<script src="/assets/js/teacher.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
