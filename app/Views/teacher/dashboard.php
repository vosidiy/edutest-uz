<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<div class="container">

    <section class="mt-5 text-white hero-strip mb-5">
        <div>
            <p class="eyebrow"><?= esc($dateLabel) ?></p>
            <h2>Welcome back, <?= esc(explode(' ', trim((string) $user['display_name']))[0]) ?></h2>
            <p class="mb-5">Build your next quiz with a focused workflow. Draft questions, configure assessment rules, and activate one stable link.</p>
            <div class="hero-actions">
                <button class="btn btn-primary" type="button" data-open-create>Create a quiz</button>
                <span data-public-page-action>
                    <?php if (! empty($user['public_page']) && ! empty($user['username'])) : ?>
                        <a class="btn btn-default" target="_blank" href="<?= esc(site_url('user/' . $user['username']), 'attr') ?>">View my page ↗ </a>
                    <?php else : ?>
                        <button class="btn btn-default" type="button" data-open-public-page>Create my page</button>
                    <?php endif ?>
                </span>
            </div>
        </div>
    </section>

    <?= $this->setData(['library' => $library, 'paginationNavigation' => $paginationNavigation])->include('teacher/partials/quiz_library') ?>

</div> <!-- container end//-->

<?= $this->include('teacher/partials/create_quiz') ?>
<?php if (empty($user['public_page']) || empty($user['username'])) : ?>
    <?= $this->setData(['user' => $user])->include('teacher/partials/public_page_dialog') ?>
<?php endif ?>
<?= $this->endSection() ?>
