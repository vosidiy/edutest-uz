<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'EduTest') ?></title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; }
        body { background: #f5f7fb; color: #182033; margin: 0; }
        main { max-width: 28rem; margin: 8vh auto; padding: 2rem; background: #fff; border-radius: .75rem; box-shadow: 0 .5rem 2rem #18203314; }
        h1 { margin-top: 0; }
        label { display: block; margin: 1rem 0 .35rem; font-weight: 600; }
        input { box-sizing: border-box; width: 100%; padding: .75rem; border: 1px solid #b9c1d0; border-radius: .4rem; font: inherit; }
        button { width: 100%; margin-top: 1.25rem; padding: .8rem; border: 0; border-radius: .4rem; background: #3157d5; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .message { padding: .8rem; border-radius: .4rem; background: #fde8e8; color: #8d1d1d; }
        .message ul { margin: 0; padding-left: 1.25rem; }
        .hint, footer { color: #5e6779; }
        footer { margin-top: 1.25rem; text-align: center; }
    </style>
</head>
<body>
<main>
    <?php $formErrors = $errors ?? session('errors') ?? []; ?>
    <?php $formError = $error ?? session('error'); ?>

    <?php if ($formError !== null) : ?>
        <p class="message" role="alert"><?= esc($formError) ?></p>
    <?php endif; ?>

    <?php if ($formErrors !== []) : ?>
        <div class="message" role="alert">
            <ul>
                <?php foreach ($formErrors as $formErrorItem) : ?>
                    <li><?= esc($formErrorItem) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
</main>
</body>
</html>
