<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<?= $this->include('teacher/partials/quiz_header') ?>

<div class="container">
    <header class="card mt-5 mb-3 p-4 page-header">
        <h3 class="mb-2"><?= esc($report['quiz']['title']) ?></h3>
        <p><span class="badge mr-2 status <?= esc($report['quiz']['status'], 'attr') ?>"><?= esc(ucfirst($report['quiz']['status'])) ?></span></p>
    </header>

    <?php $metrics = $report['metrics']; ?>
    <section class="metrics results-metrics" aria-label="Quiz result summary">
        <article class="card p-3 metric"><p><?= esc(lang('Results.finalizedAttempts')) ?></p><p class="text-secondary">Completed or provisionally expired</p><strong class="num"><?= esc((string) $metrics['finalizedAttempts']) ?></strong></article>
        <article class="card p-3 metric"><p><?= esc(lang('Results.inProgressAttempts')) ?></p><p class="text-secondary">Started, not finalized</p><strong class="num"><?= esc((string) $metrics['inProgressAttempts']) ?></strong></article>
        <article class="card p-3 metric"><p><?= esc(lang('Results.averageScore')) ?></p><p class="text-secondary">Finalized attempts</p><strong class="num"><?= $metrics['averagePercent'] === null ? '—' : esc($metrics['averagePercent']) . '%' ?></strong></article>
    </section>

    <form class="card attempt-filters" method="get">
        <label class="form-field attempt-search"><span class="form-label">Student search</span><input class="form-control" type="search" name="q" value="<?= esc($report['filters']['query'], 'attr') ?>" placeholder="Name, email, or phone"></label>
        <label class="form-field"><span class="form-label">Status</span><select class="form-control" name="status"><?php foreach (['all' => 'All statuses', 'finalized' => 'Finalized', 'in_progress' => 'In progress', 'completed' => 'Completed', 'abandoned' => 'Expired (provisional)'] as $value => $label) : ?><option value="<?= esc($value, 'attr') ?>" <?= $report['filters']['status'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select></label>
        <label class="form-field"><span class="form-label">Started from</span><input class="form-control" type="date" name="dateFrom" value="<?= esc((string) $report['filters']['dateFrom'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Started to</span><input class="form-control" type="date" name="dateTo" value="<?= esc((string) $report['filters']['dateTo'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Minimum score %</span><input class="form-control" type="number" min="0" max="100" step="0.01" name="minScore" value="<?= esc($report['filters']['minScore'] === null ? '' : (string) $report['filters']['minScore'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Maximum score %</span><input class="form-control" type="number" min="0" max="100" step="0.01" name="maxScore" value="<?= esc($report['filters']['maxScore'] === null ? '' : (string) $report['filters']['maxScore'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Sort by</span><select class="form-control" name="sort"><?php foreach (['newest' => 'Newest starts', 'oldest' => 'Oldest starts', 'score_desc' => 'Highest score', 'score_asc' => 'Lowest score', 'name_asc' => 'Student A–Z'] as $value => $label) : ?><option value="<?= esc($value, 'attr') ?>" <?= $report['filters']['sort'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select></label>
        <button class="btn btn-default attempt-filter-submit" type="submit">Apply filters</button>
    </form>

    <section class="card panel mt-4 results-list" aria-labelledby="attempt-list-heading">
        <div class="panel-heading">
            <h4 class="text-xl" id="attempt-list-heading">Attempts (<?= esc((string) $report['pagination']['total']) ?>)</h4>
        </div>
        <?php if ($report['attempts'] === []) : ?>
            <div class="empty-state"><span aria-hidden="true">◎</span><h3><?= esc(lang('Results.emptyAttemptsTitle')) ?></h3><p><?= esc(lang('Results.emptyAttemptsBody')) ?></p></div>
        <?php else : ?>
            <div class="table-wrap"><table class="table results-table"><thead><tr><th>Student</th><th>Status</th><th>Responses</th><th>Score</th><th>Duration</th><th>Finished</th><th><span class="sr-only">Open</span></th></tr></thead><tbody>
            <?php foreach ($report['attempts'] as $attempt) : ?>
                <tr>
                    <td><a class="result-title-link" href="<?= esc($attempt['url'], 'attr') ?>">
                        👤 <?= esc($attempt['name']) ?></a><small><?= esc($attempt['email'] ?? $attempt['phone'] ?? 'No optional contact') ?></small></td>
                    <td><span class="badge attempt-status <?= esc($attempt['status'], 'attr') ?>"><?= esc(ucwords(str_replace('_', ' ', $attempt['status']))) ?></span><small><?= esc($attempt['finishReason'] ?? '') ?></small><?php if ($attempt['lateSync']) : ?><small><?= esc(lang('Results.lateSync')) ?></small><?php endif ?></td>
                    <td><?= esc((string) $attempt['responsesReceived']) ?> / <?= esc($attempt['maxScore']) ?><small><?= esc((string) $attempt['correctReceived']) ?> correct</small></td>
                    <td><?= $attempt['percent'] === null ? esc(lang('Results.ungraded')) : esc($attempt['score']) . ' / ' . esc($attempt['maxScore']) . ' (' . esc($attempt['percent']) . '%)' ?></td>
                    <td><?= esc($attempt['duration'] ?? lang('Results.notAvailable')) ?></td>
                    <td><?= esc($attempt['submittedAt']['display'] ?? 'Not finished') ?></td>
                    <td><a class="btn btn-outline" href="<?= esc($attempt['url'], 'attr') ?>">Review</a></td>
                </tr>
            <?php endforeach ?>
            </tbody></table></div>
        <?php endif ?>
    </section>

    <?php if ($report['pagination']['pageCount'] > 1) : ?>
        <nav class="pagination" aria-label="Attempts pagination">
        <?php for ($page = 1; $page <= $report['pagination']['pageCount']; $page++) : ?>
            <?php $params = array_filter(['q' => $report['filters']['query'], 'status' => $report['filters']['status'], 'dateFrom' => $report['filters']['dateFrom'], 'dateTo' => $report['filters']['dateTo'], 'minScore' => $report['filters']['minScore'], 'maxScore' => $report['filters']['maxScore'], 'sort' => $report['filters']['sort'], 'page' => $page], static fn ($value): bool => $value !== null && $value !== ''); ?>
            <a class="<?= $page === $report['pagination']['page'] ? 'active' : '' ?>" href="?<?= esc(http_build_query($params), 'attr') ?>" <?= $page === $report['pagination']['page'] ? 'aria-current="page"' : '' ?>><?= esc((string) $page) ?></a>
        <?php endfor ?>
        </nav>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
