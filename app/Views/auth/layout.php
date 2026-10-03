<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'EduTest') ?></title>
    <link rel="icon" href="<?= esc(base_url('favicon.ico'), 'attr') ?>" sizes="any">
    <link rel="stylesheet" href="<?= esc(base_url('css/teacher.css') . '?v=' . filemtime(FCPATH . 'css/teacher.css'), 'attr') ?>">
</head>
<body>
<a class="skip-link" href="#auth-content">Skip to main content</a>

<div class="teacher-shell d-flex flex-col">
    <header class="teacher-topbar">
        <div class="teacher-topbar-inner">
            <a class="teacher-brand" href="<?= esc(site_url('/'), 'attr') ?>" aria-label="123test home page">
                <svg class="brand-mark" aria-hidden="true" focusable="false" viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="auth-brand-gradient" x1="18" x2="34" y1="0" y2="36" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#a5b4fc"/>
                            <stop offset="1" stop-color="#6163fe"/>
                        </linearGradient>
                    </defs>
                    <rect width="36" height="36" rx="12" fill="url(#auth-brand-gradient)"/>
                    <rect x="7" y="7" width="10" height="10" rx="4" fill="#4338ca"/>
                    <rect x="19" y="7" width="10" height="10" rx="4" fill="#4338ca"/>
                    <rect x="7" y="19" width="10" height="10" rx="4" fill="#4338ca"/>
                    <rect x="19" y="19" width="10" height="10" rx="4" fill="#4338ca"/>
                </svg>
                <span>123test</span>
            </a>
            <a class="btn btn-default" href="<?= esc(site_url('/') . '#faq', 'attr') ?>">Help</a>
        </div>
    </header>

    <main class="teacher-main content-area d-flex flex-grow flex-center" id="auth-content">
        <section class="card shadow-md w-full max-w-md">
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
</div>
</body>
</html>
