<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<header class="page-header">
    <div>
        <p class="eyebrow"><?= esc($dateLabel) ?></p>
        <h1>Welcome back, <?= esc(explode(' ', trim((string) $user['display_name']))[0]) ?></h1>
    </div>
    <button class="button primary" type="button" data-open-create>＋ New quiz</button>
</header>

<div class="content-area">
    <section class="hero-strip">
        <div>
            <p class="kicker">Ready when you are</p>
            <h2>Build your next quiz with a focused workflow.</h2>
            <p>Draft questions, configure assessment rules, preview the experience, and publish one stable link.</p>
            <button class="button light" type="button" data-open-create>Create a quiz</button>
        </div>
    </section>

    <?php $metrics = $dashboard['metrics']; ?>
    <section class="metrics" aria-label="Workspace summary">
        <article class="metric"><span>Total quizzes</span><strong><?= esc((string) $metrics['totalQuizzes']) ?></strong><small>Active library</small></article>
        <article class="metric"><span>Published</span><strong><?= esc((string) $metrics['publishedQuizzes']) ?></strong><small>Available links</small></article>
        <article class="metric"><span>Assessment submissions</span><strong><?= esc((string) $metrics['assessmentSubmissions']) ?></strong><small>Finalized attempts</small></article>
        <article class="metric"><span>Practice starts</span><strong><?= esc((string) $metrics['practiceStarts']) ?></strong><small>Anonymous, deduplicated</small></article>
    </section>

    <section class="panel recent-panel">
        <div class="panel-heading"><div><h2>Recent quizzes</h2><p>Your latest authoring activity.</p></div><a href="<?= site_url('quizzes') ?>">View all →</a></div>
        <?php if ($dashboard['recent'] === []) : ?>
            <div class="empty-state"><span>▤</span><h3>No quizzes yet</h3><p>Create your first draft to start building.</p><button class="button primary" type="button" data-open-create>New quiz</button></div>
        <?php else : ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Quiz</th><th>Mode</th><th>Status</th><th>Questions</th><th>Responses</th></tr></thead><tbody>
            <?php foreach ($dashboard['recent'] as $quiz) : ?>
                <tr data-href="<?= esc($quiz['editUrl'], 'attr') ?>" tabindex="0">
                    <td><strong><?= esc($quiz['title']) ?></strong><small data-date="<?= esc((string) $quiz['updatedAt'], 'attr') ?>"></small></td>
                    <td><?= esc(ucfirst($quiz['mode'])) ?></td><td><span class="status <?= esc($quiz['status'], 'attr') ?>"><?= esc(ucfirst($quiz['status'])) ?></span></td>
                    <td><?= esc((string) $quiz['questionCount']) ?></td><td><?= esc((string) $quiz['responseCount']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody></table></div>
        <?php endif ?>
    </section>
</div>

<?= $this->include('teacher/partials/create_quiz') ?>
<?= $this->endSection() ?>
