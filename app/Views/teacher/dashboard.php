<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<header class="page-header">
    <div>
        <p class="eyebrow"><?= esc($dateLabel) ?></p>
        <h1>Welcome back, <?= esc(explode(' ', trim((string) $user['display_name']))[0]) ?></h1>
    </div>
    <button class="btn btn-primary" type="button" data-open-create aria-label="<?= esc(lang('Workspace.newQuiz'), 'attr') ?>"><span aria-hidden="true">＋</span><span class="btn-label">New quiz</span></button>
</header>

<div class="content-area">
    <section class="hero-strip">
        <div>
            <p class="kicker">Ready when you are</p>
            <h2>Build your next quiz with a focused workflow.</h2>
            <p>Draft questions, configure assessment rules, preview the experience, and publish one stable link.</p>
            <button class="btn btn-default" type="button" data-open-create>Create a quiz</button>
        </div>
    </section>

    <?php $metrics = $dashboard['metrics']; ?>
    <section class="metrics dashboard-metrics" aria-label="<?= esc(lang('Workspace.summary'), 'attr') ?>">
        <?php foreach ([
            ['activeQuizzes', 'totalQuizzes', 'activeLibrary'],
            ['published', 'publishedQuizzes', 'availableLinks'],
            ['finalized', 'assessmentSubmissions', 'finalizedHint'],
            ['inProgress', 'inProgressAttempts', 'inProgressHint'],
            ['average', 'averagePercent', 'averageHint'],
            ['practiceStarts', 'practiceStarts', 'practiceHint'],
        ] as [$label, $key, $hint]) : ?>
            <article class="card metric"><span><?= esc(lang('Workspace.' . $label)) ?></span><strong><?= $metrics[$key] === null ? '—' : esc((string) $metrics[$key]) . ($key === 'averagePercent' ? '%' : '') ?></strong><small><?= esc(lang('Workspace.' . $hint)) ?></small></article>
        <?php endforeach ?>
    </section>
    <?php $library = $dashboard['library']; ?>
    <?= $this->setData(['library' => $library])->include('teacher/partials/quiz_library') ?>
</div>

<?= $this->include('teacher/partials/create_quiz') ?>
<?= $this->endSection() ?>
