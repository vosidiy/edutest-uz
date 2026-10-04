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
    <link rel="stylesheet" href="<?= esc(base_url('css/teacher.css') . '?v=' . filemtime(FCPATH . 'css/teacher.css'), 'attr') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body class="<?= esc(($quizWorkspace ?? false) ? 'quiz-workspace' . (($builderHeader ?? false) ? ' builder-page' : '') : 'dashboard-page', 'attr') ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="teacher-shell">
    <?php if (! ($quizWorkspace ?? false)) : ?>
        <header class="teacher-topbar">
            <div class="container teacher-topbar-inner">
                <a class="brand" href="<?= site_url('dashboard') ?>" aria-label="123test Dashboard">
                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g>
                        <rect width="36" height="36" rx="12" fill="url(#brand-gradient)"></rect>
                        <rect x="7" y="7" width="10" height="10" rx="4" fill="#4338ca"></rect>
                        <rect x="19" y="7" width="10" height="10" rx="4" fill="#4338ca"></rect>
                        <path d="M7 23C7 20.7909 8.79086 19 11 19H13C15.2091 19 17 20.7909 17 23V25C17 27.2091 15.2091 29 13 29H11C8.79086 29 7 27.2091 7 25V23Z" fill="#4338ca"></path>
                        <path d="M19 11C19 8.79086 20.7909 7 23 7H25C27.2091 7 29 8.79086 29 11V13C29 15.2091 27.2091 17 25 17H23C20.7909 17 19 15.2091 19 13V11Z" fill="#4338ca"></path>
                        <path d="M19 23C19 20.7909 20.7909 19 23 19H25C27.2091 19 29 20.7909 29 23V25C29 27.2091 27.2091 29 25 29H23C20.7909 29 19 27.2091 19 25V23Z" fill="#4338ca"></path>
                        </g>
                        <defs>
                        <linearGradient id="brand-gradient" x1="18" x2="34" y1="0" y2="36" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#a5b4fc"></stop>
                            <stop offset="1" stop-color="#6163fe"></stop>
                        </linearGradient>
                        </defs>
                    </svg>
                    
                    <span style="font-weight:700">123<em style="padding-left:4px">test</em></span> 
                </a>
          
                <?php
                $displayName = (string) ($user['display_name'] ?? 'Teacher');
                $parts = preg_split('/\s+/u', trim($displayName)) ?: [];
                $initials = '';
                foreach (array_slice($parts, 0, 2) as $part) $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                ?>
                <details class="account-menu" data-account-menu>
                    <summary aria-label="<?= esc(lang('Workspace.account') . ': ' . $displayName, 'attr') ?>"><span class="avatar" aria-hidden="true"><?= esc($initials ?: 'T') ?></span><strong><?= esc($displayName) ?></strong><span aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down preview-icon"><path d="m6 9 6 6 6-6"/></svg>
                    </span></summary>
                    <div class="dropdown">
                        <a class="dropdown-item" href="<?= site_url('account') ?>"><?= esc(lang('Workspace.myAccount')) ?></a>
                        <hr>
                        <form action="<?= site_url('logout') ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-red" type="submit"><?= esc(lang('Workspace.signOut')) ?></button>
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
<script src="<?= esc(base_url('js/teacher.js') . '?v=' . filemtime(FCPATH . 'js/teacher.js'), 'attr') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
