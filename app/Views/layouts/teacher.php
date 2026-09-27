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
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/teacher.css') . '?v=' . filemtime(FCPATH . 'assets/css/teacher.css'), 'attr') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body class="<?= esc(($quizWorkspace ?? false) ? 'quiz-workspace' . (($builderHeader ?? false) ? ' builder-page' : '') : 'dashboard-page', 'attr') ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="teacher-shell">
    <?php if (! ($quizWorkspace ?? false)) : ?>
        <header class="teacher-topbar">
            <div class="teacher-topbar-inner">
                <a class="teacher-brand" href="<?= site_url('dashboard') ?>" aria-label="<?= esc(lang('Workspace.dashboardHome'), 'attr') ?>"><span class="brand-mark" aria-hidden="true">E</span><span>EduTest</span></a>
                <?php
                $displayName = (string) ($user['display_name'] ?? 'Teacher');
                $parts = preg_split('/\s+/u', trim($displayName)) ?: [];
                $initials = '';
                foreach (array_slice($parts, 0, 2) as $part) $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                ?>
                <details class="account-menu" data-account-menu>
                    <summary aria-label="<?= esc(lang('Workspace.account') . ': ' . $displayName, 'attr') ?>"><span class="avatar" aria-hidden="true"><?= esc($initials ?: 'T') ?></span><strong><?= esc($displayName) ?></strong><span aria-hidden="true">⌄</span></summary>
                    <div class="account-popover">
                        <form action="<?= site_url('logout') ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="btn btn-default" type="submit"><?= esc(lang('Workspace.signOut')) ?></button>
                        </form>
                    </div>
                </details>
            </div>
        </header>
    <?php endif ?>
    <main class="teacher-main" id="main-content">
        <?= $this->renderSection('content') ?>
    </main>
</div>
<div class="app-toast" id="app-toast" role="status" aria-live="polite"></div>
<script src="<?= esc(base_url('assets/js/teacher.js') . '?v=' . filemtime(FCPATH . 'assets/js/teacher.js'), 'attr') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
