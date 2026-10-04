<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<div class="container">

    <section class="mt-5 text-white hero-strip mb-5">
        <div>
            <p class="eyebrow"><?= esc($dateLabel) ?></p>
            <h2>Welcome back, <?= esc(explode(' ', trim((string) $user['display_name']))[0]) ?></h2>
            <p>Build your next quiz with a focused workflow. Draft questions, configure assessment rules, preview the experience, and publish one stable link.</p>
            <button class="btn btn-primary" type="button" data-open-create>Create a quiz</button>
        </div>
    </section>

    <?= $this->setData(['library' => $library, 'paginationNavigation' => $paginationNavigation])->include('teacher/partials/quiz_library') ?>

</div> <!-- container end//-->

<?= $this->include('teacher/partials/create_quiz') ?>
<?= $this->endSection() ?>
