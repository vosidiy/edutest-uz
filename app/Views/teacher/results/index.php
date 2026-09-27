<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<header class="page-header results-page-header">
    <div>
        <p class="eyebrow"><?= esc(lang('Results.overviewEyebrow')) ?></p>
        <h1><?= esc(lang('Results.overviewTitle')) ?></h1>
        <p><?= esc(lang('Results.overviewIntro')) ?></p>
    </div>
</header>

<div class="content-area results-workspace">
    <?php $metrics = $report['metrics']; ?>
    <section class="metrics results-metrics" aria-label="<?= esc(lang('Results.overviewTitle'), 'attr') ?>">
        <article class="card metric"><span><?= esc(lang('Results.finalizedAttempts')) ?></span><strong><?= esc((string) $metrics['finalizedAttempts']) ?></strong><small>Submitted or expired</small></article>
        <article class="card metric"><span><?= esc(lang('Results.inProgressAttempts')) ?></span><strong><?= esc((string) $metrics['inProgressAttempts']) ?></strong><small>Saved, not finalized</small></article>
        <article class="card metric"><span><?= esc(lang('Results.averageScore')) ?></span><strong><?= $metrics['averagePercent'] === null ? '—' : esc($metrics['averagePercent']) . '%' ?></strong><small><?= $metrics['averagePercent'] === null ? esc(lang('Results.noScore')) : 'Finalized attempts only' ?></small></article>
    </section>

    <form class="card results-toolbar" method="get" action="<?= site_url('results') ?>">
        <label class="form-field results-search">
            <span class="form-label">Search assessment quizzes</span>
            <input class="form-control" type="search" name="q" value="<?= esc($report['filters']['query'], 'attr') ?>" placeholder="Quiz title">
        </label>
        <label class="form-field">
            <span class="form-label">Lifecycle</span>
            <select class="form-control" name="lifecycle">
                <?php foreach (['all' => 'All history', 'active' => 'Active', 'archived' => 'Archived', 'deleted' => 'Deleted'] as $value => $label) : ?>
                    <option value="<?= esc($value, 'attr') ?>" <?= $report['filters']['lifecycle'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label class="form-field">
            <span class="form-label">Sort by</span>
            <select class="form-control" name="sort">
                <?php foreach (['updated_desc' => 'Recently updated', 'submissions_desc' => 'Most finalized', 'score_desc' => 'Highest average', 'title_asc' => 'Title A–Z'] as $value => $label) : ?>
                    <option value="<?= esc($value, 'attr') ?>" <?= $report['filters']['sort'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <button class="btn btn-default results-filter-submit" type="submit">Apply filters</button>
    </form>

    <section class="card panel results-list" aria-labelledby="assessment-results-heading">
        <div class="panel-heading">
            <div><h2 id="assessment-results-heading">Assessment quizzes</h2><p><?= esc((string) $report['pagination']['total']) ?> matching quizzes</p></div>
        </div>
        <?php if ($report['rows'] === []) : ?>
            <div class="empty-state"><span aria-hidden="true">▥</span><h3><?= esc(lang('Results.emptyOverviewTitle')) ?></h3><p><?= esc(lang('Results.emptyOverviewBody')) ?></p></div>
        <?php else : ?>
            <div class="table-wrap">
                <table class="table results-table">
                    <thead><tr><th scope="col">Quiz</th><th scope="col">Lifecycle</th><th scope="col">Finalized</th><th scope="col">In progress</th><th scope="col">Average</th><th scope="col">Latest submission</th><th scope="col"><span class="sr-only">Open report</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($report['rows'] as $quiz) : ?>
                        <tr>
                            <td><a class="result-title-link" href="<?= esc($quiz['url'], 'attr') ?>"><?= esc($quiz['title']) ?></a><small>Updated <?= esc($quiz['updatedAt']['display'] ?? lang('Results.notAvailable')) ?></small></td>
                            <td><span class="badge status <?= esc($quiz['status'], 'attr') ?>"><?= esc(ucfirst($quiz['status'])) ?></span></td>
                            <td><?= esc((string) $quiz['finalizedCount']) ?></td>
                            <td><?= esc((string) $quiz['inProgressCount']) ?></td>
                            <td><?= $quiz['averagePercent'] === null ? '—' : esc($quiz['averagePercent']) . '%' ?></td>
                            <td><?= esc($quiz['latestSubmission']['display'] ?? 'No submissions') ?></td>
                            <td><a class="btn btn-sm btn-outline" href="<?= esc($quiz['url'], 'attr') ?>"><?= esc(lang('Results.viewQuizResults')) ?></a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <?php if ($report['pagination']['pageCount'] > 1) : ?>
        <?php
        $currentPage = $report['pagination']['page'];
        $lastPage = $report['pagination']['pageCount'];
        $pages = array_values(array_unique(array_filter([1, $currentPage - 2, $currentPage - 1, $currentPage, $currentPage + 1, $currentPage + 2, $lastPage], static fn (int $page): bool => $page >= 1 && $page <= $lastPage)));
        sort($pages);
        ?>
        <nav class="pagination" aria-label="Results pagination">
            <?php $previous = 0; foreach ($pages as $page) : ?>
                <?php if ($previous !== 0 && $page > $previous + 1) : ?><span aria-hidden="true">…</span><?php endif ?>
                <?php $params = array_filter(['q' => $report['filters']['query'], 'lifecycle' => $report['filters']['lifecycle'] !== 'all' ? $report['filters']['lifecycle'] : null, 'sort' => $report['filters']['sort'] !== 'updated_desc' ? $report['filters']['sort'] : null, 'page' => $page], static fn ($value): bool => $value !== null && $value !== ''); ?>
                <a class="<?= $page === $currentPage ? 'active' : '' ?>" href="?<?= esc(http_build_query($params), 'attr') ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= esc((string) $page) ?></a>
                <?php $previous = $page; ?>
            <?php endforeach ?>
        </nav>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
