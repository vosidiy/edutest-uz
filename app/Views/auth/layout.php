<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'EduTest') ?></title>
    <link rel="icon" href="<?= esc(base_url('favicon.ico'), 'attr') ?>" sizes="any">
    <link rel="stylesheet" href="<?= esc(base_url('css/teacher.css') . '?v=' . filemtime(FCPATH . 'css/teacher.css'), 'attr') ?>">
    <script src="<?= esc(base_url('js/auth.js') . '?v=' . filemtime(FCPATH . 'js/auth.js'), 'attr') ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#auth-content">Skip to main content</a>


<header class="teacher-topbar">
    <div class="teacher-topbar-inner">
        <a class="brand" href="#header" aria-label="123test bosh sahifasi">
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
        <a class="btn btn-default" href="<?= esc(site_url('/') . '#faq', 'attr') ?>">Help</a>
    </div>
</header>

<main class="teacher-main d-flex flex-grow flex-center" id="auth-content" style="padding-top:3rem">
    <section class="card shadow-md w-full" style="max-width:400px">
        <div class="card-body">
            <?php $formErrors = $errors ?? session('errors') ?? []; ?>
            <?php $formError = $error ?? session('error'); ?>

            <?php if ($formError !== null) : ?>
                <p class="alert alert-danger mb-5" role="alert"><?= esc($formError) ?></p>
            <?php endif; ?>

            <?php if ($formErrors !== []) : ?>
                <div class="alert alert-danger mb-5" role="alert">
                    <ul>
                        <?php foreach ($formErrors as $formErrorItem) : ?>
                            <li><?= esc($formErrorItem) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>
        </div>
    </section>
</main>
</body>
</html>
