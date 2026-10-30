<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<?= $this->include('teacher/partials/quiz_header') ?>

<div class="container">
    <header class="mt-6 mb-4">
        <h3 class="mb-2"><?= esc($report['quiz']['title']) ?></h3>
    </header>

    <form class="card attempt-filters" method="get">
        <label class="form-field attempt-search"><span class="form-label">Student search</span><input class="form-control" type="search" name="q" value="<?= esc($report['filters']['query'], 'attr') ?>" placeholder="Name, email, or phone"></label>
        <label class="form-field"><span class="form-label">Minimum score %</span><input class="form-control" type="number" min="0" max="100" step="0.01" name="minScore" value="<?= esc($report['filters']['minScore'] === null ? '' : (string) $report['filters']['minScore'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Date</span><select class="form-control" name="dateRange"><?php foreach (['last_24_hours' => 'Last 24 hours', 'yesterday' => 'Yesterday', 'last_7_days' => 'Last 7 days', 'last_30_days' => 'Last 30 days', 'all' => 'All time'] as $value => $label) : ?><option value="<?= esc($value, 'attr') ?>" <?= $report['filters']['dateRange'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select></label>
        <button class="btn btn-default attempt-filter-submit" type="submit">Apply filters</button>
    </form>

    <section class="card panel mt-4 results-list" aria-labelledby="attempt-list-heading">
        <div class="panel-heading">
            <h4 class="text-xl" id="attempt-list-heading">Attempts (<?= esc((string) $report['pagination']['total']) ?>)</h4>
        </div>
        <?php if ($report['attempts'] === []) : ?>
            <div class="empty-state"><span aria-hidden="true">◎</span><h3><?= esc(lang('Results.emptyAttemptsTitle')) ?></h3><p><?= esc(lang('Results.emptyAttemptsBody')) ?></p></div>
        <?php else : ?>
            <div class="table-wrap"><table class="table results-table"><thead><tr><th>Student</th><th>Status</th><th>Responses</th><th>Score</th><th>Duration (min)</th><th>Finished</th><th><span class="sr-only">Open</span></th></tr></thead><tbody>
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
            <?php $params = array_filter(['q' => $report['filters']['query'], 'minScore' => $report['filters']['minScore'], 'dateRange' => $report['filters']['dateRange'], 'page' => $page], static fn ($value): bool => $value !== null && $value !== ''); ?>
            <a class="<?= $page === $report['pagination']['page'] ? 'active' : '' ?>" href="?<?= esc(http_build_query($params), 'attr') ?>" <?= $page === $report['pagination']['page'] ? 'aria-current="page"' : '' ?>><?= esc((string) $page) ?></a>
        <?php endfor ?>
        </nav>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
