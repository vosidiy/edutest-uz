<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<?= $this->include('teacher/partials/quiz_header') ?>

<div class="container">

    <header class="card mt-5 mb-5 p-4 page-header">

        <div>
             <h2 class="mb-2"><?= esc($report['quiz']['title']) ?></h2>
        </div>
        
        <p>
            <span class="badge mr-2 status <?= esc($report['quiz']['status'], 'attr') ?>"><?= esc(ucfirst($report['quiz']['status'])) ?></span>    
            <?= esc(lang('Results.currentMode')) ?>: <?= esc(ucfirst($report['quiz']['currentMode'])) ?>
        </p>
                   
        <hr>
        <p>Finalized totals and saved work in progress. <?= esc(lang('Results.revisionRetentionNotice')) ?></p>

    </header>

    <p class="alert alert-warning"><?= esc(lang('Results.progressNotice')) ?> <?= esc(lang('Results.abandonedNotice')) ?></p>


    <?php $metrics = $report['metrics']; ?>
    <section class="metrics results-metrics" aria-label="Quiz result summary">
        <article class="card p-3 metric">
            <p><?= esc(lang('Results.finalizedAttempts')) ?></p>
            <p class="text-secondary">Completed or abandoned</p>
            <strong class="num"><?= esc((string) $metrics['finalizedAttempts']) ?></strong>
        </article>
        <article class="card p-3 metric">
            <p><?= esc(lang('Results.inProgressAttempts')) ?></p>
            <p class="text-secondary">Saved, not finalized</p>
            <strong class="num"><?= esc((string) $metrics['inProgressAttempts']) ?></strong>
        </article>
        <article class="card p-3 metric">
            <p><?= esc(lang('Results.averageScore')) ?></p>
            <p  class="text-secondary"><?= $metrics['averagePercent'] === null ? esc(lang('Results.noScore')) : 'Finalized attempts' ?></p>
            <strong class="num"><?= $metrics['averagePercent'] === null ? '—' : esc($metrics['averagePercent']) . '%' ?>
            </strong>
        </article>
    </section>

    <form class="card attempt-filters" method="get">
        <label class="form-field attempt-search"><span class="form-label">Student search</span><input class="form-control" type="search" name="q" value="<?= esc($report['filters']['query'], 'attr') ?>" placeholder="Name, email, or phone"></label>
        <label class="form-field"><span class="form-label">Status</span><select class="form-control" name="status"><?php foreach (['all' => 'All statuses', 'finalized' => 'Finalized', 'in_progress' => 'In progress', 'completed' => 'Completed', 'abandoned' => 'Abandoned'] as $value => $label) : ?><option value="<?= esc($value, 'attr') ?>" <?= $report['filters']['status'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select></label>
        <label class="form-field"><span class="form-label">Started from</span><input class="form-control" type="date" name="dateFrom" value="<?= esc((string) $report['filters']['dateFrom'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Started to</span><input class="form-control" type="date" name="dateTo" value="<?= esc((string) $report['filters']['dateTo'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Minimum score %</span><input class="form-control" type="number" min="0" max="100" step="0.01" name="minScore" value="<?= esc($report['filters']['minScore'] === null ? '' : (string) $report['filters']['minScore'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Maximum score %</span><input class="form-control" type="number" min="0" max="100" step="0.01" name="maxScore" value="<?= esc($report['filters']['maxScore'] === null ? '' : (string) $report['filters']['maxScore'], 'attr') ?>"></label>
        <label class="form-field"><span class="form-label">Integrity events</span><select class="form-control" name="integrity"><option value="all">All attempts</option><option value="flagged" <?= $report['filters']['integrity'] === 'flagged' ? 'selected' : '' ?>>Has recorded events</option><option value="clear" <?= $report['filters']['integrity'] === 'clear' ? 'selected' : '' ?>>No recorded events</option></select></label>
        <label class="form-field"><span class="form-label">Sort by</span><select class="form-control" name="sort"><?php foreach (['newest' => 'Newest starts', 'oldest' => 'Oldest starts', 'score_desc' => 'Highest score', 'score_asc' => 'Lowest score', 'name_asc' => 'Student A–Z'] as $value => $label) : ?><option value="<?= esc($value, 'attr') ?>" <?= $report['filters']['sort'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select></label>
        <button class="btn btn-default attempt-filter-submit" type="submit">Apply filters</button>
    </form>

    <section class="card panel results-list" aria-labelledby="attempt-list-heading">
        <div class="panel-heading"><div><h2 id="attempt-list-heading">Saved attempts</h2><p><?= esc((string) $report['pagination']['total']) ?> matching attempts</p></div></div>
        <?php if ($report['attempts'] === []) : ?>
            <div class="empty-state"><span aria-hidden="true">◎</span><h3><?= esc(lang('Results.emptyAttemptsTitle')) ?></h3><p><?= esc(lang('Results.emptyAttemptsBody')) ?></p></div>
        <?php else : ?>
            <div class="table-wrap"><table class="table results-table"><thead><tr><th scope="col">Student</th><th scope="col">Paper</th><th scope="col">Status</th><th scope="col"><?= esc(lang('Results.responsesReceived')) ?></th><th scope="col">Score</th><th scope="col">Duration</th><th scope="col">Finished</th><th scope="col">Integrity</th><th scope="col"><span class="sr-only">Open attempt</span></th></tr></thead><tbody>
            <?php foreach ($report['attempts'] as $attempt) : ?>
                <tr>
                    <td><a class="result-title-link" href="<?= esc($attempt['url'], 'attr') ?>"><?= esc($attempt['name']) ?></a><small><?= esc($attempt['email'] ?? $attempt['phone'] ?? 'No optional contact') ?></small></td>
                    <td><?= esc(lang('Results.paperRevision')) ?> <?= esc((string) $attempt['paperRevision']) ?></td>
                    <td><span class="badge attempt-status <?= esc($attempt['status'], 'attr') ?>"><?= esc($attempt['finishReason'] === 'quit' ? lang('Results.quitStatus') : ucwords(str_replace('_', ' ', $attempt['status']))) ?></span><small><?= esc($attempt['finishReason'] ? lang('Player.ui.' . $attempt['finishReason']) : '') ?></small><?php if ($attempt['lateSync']) : ?><small><?= esc(lang('Results.lateSync')) ?></small><?php endif ?></td>
                    <td><?= esc((string) $attempt['responsesReceived']) ?> / <?= esc($attempt['maxScore']) ?><small><?= esc((string) $attempt['correctReceived']) ?> <?= esc(lang($attempt['status'] === 'in_progress' ? 'Results.correctSoFar' : 'Results.correctReceived')) ?></small></td>
                    <td><?= $attempt['percent'] === null ? esc(lang('Results.ungraded')) : esc($attempt['score']) . ' / ' . esc($attempt['maxScore']) . ' questions (' . esc($attempt['percent']) . '%)' ?></td>
                    <td><?= esc($attempt['duration'] ?? lang('Results.notAvailable')) ?></td>
                    <td><?= esc($attempt['submittedAt']['display'] ?? 'Not finished') ?></td>
                    <td><span class="integrity-count <?= $attempt['integrityCount'] > 0 ? 'has-events' : '' ?>"><?= esc((string) $attempt['integrityCount']) ?></span></td>
                    <td><a class="btn btn-sm btn-outline" href="<?= esc($attempt['url'], 'attr') ?>">Review</a></td>
                </tr>
            <?php endforeach ?>
            </tbody></table></div>
        <?php endif ?>
    </section>

    <?php if ($report['pagination']['pageCount'] > 1) : ?>
        <?php $currentPage = $report['pagination']['page']; $lastPage = $report['pagination']['pageCount']; $pages = array_values(array_unique(array_filter([1, $currentPage - 2, $currentPage - 1, $currentPage, $currentPage + 1, $currentPage + 2, $lastPage], static fn (int $page): bool => $page >= 1 && $page <= $lastPage))); sort($pages); ?>
        <nav class="pagination" aria-label="Attempts pagination">
            <?php $previous = 0; foreach ($pages as $page) : ?>
                <?php if ($previous !== 0 && $page > $previous + 1) : ?><span aria-hidden="true">…</span><?php endif ?>
                <?php $params = array_filter(['q' => $report['filters']['query'], 'status' => $report['filters']['status'], 'dateFrom' => $report['filters']['dateFrom'], 'dateTo' => $report['filters']['dateTo'], 'minScore' => $report['filters']['minScore'], 'maxScore' => $report['filters']['maxScore'], 'integrity' => $report['filters']['integrity'], 'sort' => $report['filters']['sort'], 'page' => $page], static fn ($value): bool => $value !== null && $value !== ''); ?>
                <a class="<?= $page === $currentPage ? 'active' : '' ?>" href="?<?= esc(http_build_query($params), 'attr') ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= esc((string) $page) ?></a>
                <?php $previous = $page; ?>
            <?php endforeach ?>
        </nav>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
