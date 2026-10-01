<section class="dashboard-library" aria-labelledby="quiz-library-title">
    <div class="library-heading"><h2 id="quiz-library-title"><?= esc(lang('Workspace.library')) ?></h2><p><?= esc(lang('Workspace.libraryHint')) ?></p></div>
    <nav class="tabs library-tabs" aria-label="<?= esc(lang('Workspace.views'), 'attr') ?>">
        <?php foreach (['all', 'active', 'archived', 'trash'] as $view) : ?>
            <?php $params = array_replace($library['filters'], ['view' => $view, 'page' => 1]); ?>
            <a class="<?= $library['view'] === $view ? 'active' : '' ?>" <?= $library['view'] === $view ? 'aria-current="page"' : '' ?> href="<?= esc(site_url('dashboard') . '?' . http_build_query($params), 'attr') ?>"><?= esc(lang('Workspace.view.' . $view)) ?></a>
        <?php endforeach ?>
    </nav>
    <form class="library-toolbar" method="get" action="<?= site_url('dashboard') ?>">
        <input type="hidden" name="view" value="<?= esc($library['view'], 'attr') ?>">
        <label class="search"><span class="sr-only"><?= esc(lang('Workspace.search')) ?></span><input class="form-control" type="search" name="q" value="<?= esc($library['filters']['q'], 'attr') ?>" placeholder="<?= esc(lang('Workspace.search'), 'attr') ?>"></label>
        <label><span class="sr-only"><?= esc(lang('Workspace.statusLabel')) ?></span><select class="form-control" name="status"><option value=""><?= esc(lang('Workspace.allStatuses')) ?></option><?php foreach (['draft', 'published', 'closed', 'archived'] as $status) : ?><option value="<?= $status ?>" <?= $library['filters']['status'] === $status ? 'selected' : '' ?>><?= esc(lang('Workspace.status.' . $status)) ?></option><?php endforeach ?></select></label>
        <label><span class="sr-only"><?= esc(lang('Workspace.modeLabel')) ?></span><select class="form-control" name="mode"><option value=""><?= esc(lang('Workspace.allModes')) ?></option><?php foreach (['assessment', 'practice'] as $mode) : ?><option value="<?= $mode ?>" <?= $library['filters']['mode'] === $mode ? 'selected' : '' ?>><?= esc(lang('Workspace.mode.' . $mode)) ?></option><?php endforeach ?></select></label>
        <label><span class="sr-only"><?= esc(lang('Workspace.reportsLabel')) ?></span><select class="form-control" name="reports"><option value=""><?= esc(lang('Workspace.allQuizzes')) ?></option><option value="1" <?= $library['filters']['reports'] === '1' ? 'selected' : '' ?>><?= esc(lang('Workspace.reportEligible')) ?></option></select></label>
        <label><span class="sr-only"><?= esc(lang('Workspace.sortLabel')) ?></span><select class="form-control" name="sort"><?php foreach (['updated_desc', 'created_desc', 'title_asc', 'submissions_desc', 'score_desc'] as $sort) : ?><option value="<?= $sort ?>" <?= $library['filters']['sort'] === $sort ? 'selected' : '' ?>><?= esc(lang('Workspace.sort.' . $sort)) ?></option><?php endforeach ?></select></label>
        <button class="btn btn-default" type="submit"><?= esc(lang('Workspace.apply')) ?></button>
    </form>
    <div class="card quiz-list">
        <?php if ($library['rows'] === []) : ?>
            <div class="empty-state"><span aria-hidden="true">▤</span><h3><?= esc(lang('Workspace.emptyTitle')) ?></h3><p><?= esc(lang('Workspace.emptyBody')) ?></p><button class="btn btn-primary" type="button" data-open-create><?= esc(lang('Workspace.newQuiz')) ?></button></div>
        <?php endif ?>
        <?php foreach ($library['rows'] as $quiz) : ?>
            <article class="quiz-row" data-quiz-id="<?= esc($quiz['publicId'], 'attr') ?>">
                <div class="quiz-main">
                    <span class="quiz-symbol" aria-hidden="true">▤</span>
                    <div class="quiz-row-title">
                        <h3><?php if ($quiz['editUrl'] !== null || $quiz['resultsUrl'] !== null) : ?><a href="<?= esc($quiz['editUrl'] ?? $quiz['resultsUrl'], 'attr') ?>"><?= esc($quiz['title']) ?></a><?php else : ?><?= esc($quiz['title']) ?><?php endif ?></h3>
                        <small><?= esc(lang('Workspace.questions', [$quiz['questionCount']])) ?> · <?= esc(lang('Workspace.mode.' . $quiz['mode'])) ?><?= $quiz['hasPublished'] ? ' · ' . esc(lang('Workspace.hasPublishedPaper')) : '' ?></small>
                    </div>
                    <span class="badge status <?= esc($quiz['deleted'] ? 'deleted' : $quiz['status'], 'attr') ?>"><?= esc(lang('Workspace.status.' . ($quiz['deleted'] ? 'deleted' : $quiz['status']))) ?></span>
                </div>
                <div class="quiz-row-actions">
                    <?php if ($quiz['editUrl'] !== null) : ?><a class="btn btn-default btn-sm" href="<?= esc($quiz['editUrl'], 'attr') ?>"><?= esc(lang('EduTest.builder.navigation.builder')) ?></a><?php endif ?>
                    <?php if ($quiz['resultsUrl'] !== null) : ?><a class="btn btn-outline btn-sm" href="<?= esc($quiz['resultsUrl'], 'attr') ?>"><?= esc(lang('EduTest.builder.navigation.responses')) ?></a><?php endif ?>
                    <div class="action-menu">
                        <button class="btn btn-default btn-icon" type="button" data-menu-toggle aria-expanded="false" aria-label="<?= esc(lang('Workspace.actions') . ': ' . $quiz['title'], 'attr') ?>">•••</button>
                        <div class="menu" hidden>
                            <?php if ($quiz['deleted']) : ?>
                                <button class="btn btn-link menu-item" type="button" data-quiz-action="restore"><?= esc(lang('Workspace.action.restore')) ?></button>
                            <?php else : ?>
                                <button class="btn btn-link menu-item" type="button" data-quiz-action="duplicate"><?= esc(lang('Workspace.action.duplicate')) ?></button>
                                <?php foreach (['draft' => 'publish', 'published' => 'close', 'closed' => 'reopen'] as $status => $action) : ?>
                                    <?php if ($quiz['status'] === $status) : ?><button class="btn btn-link menu-item" type="button" data-quiz-action="<?= $action ?>"><?= esc(lang('Workspace.action.' . $action)) ?></button><?php endif ?>
                                <?php endforeach ?>
                                <?php $action = $quiz['status'] === 'archived' ? 'unarchive' : 'archive'; ?>
                                <button class="btn btn-link menu-item" type="button" data-quiz-action="<?= $action ?>"><?= esc(lang('Workspace.action.' . $action)) ?></button>
                                <?php if ($quiz['status'] !== 'published') : ?><button class="btn btn-link menu-item danger" type="button" data-quiz-action="trash"><?= esc(lang('Workspace.action.trash')) ?></button><?php endif ?>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <dl class="quiz-row-stats">
                    <div><dt><?= esc(lang('Workspace.finalized')) ?></dt><dd><?= esc((string) $quiz['assessmentSubmissions']) ?></dd></div>
                    <div><dt><?= esc(lang('Workspace.inProgress')) ?></dt><dd><?= esc((string) $quiz['inProgressAttempts']) ?></dd></div>
                    <div><dt><?= esc(lang('Workspace.average')) ?></dt><dd><?= $quiz['averagePercent'] === null ? '—' : esc($quiz['averagePercent']) . '%' ?></dd></div>
                    <div><dt><?= esc(lang('Workspace.practiceStarts')) ?></dt><dd><?= esc((string) $quiz['practiceStarts']) ?></dd></div>
                    <div><dt><?= esc(lang('Workspace.latestSubmission')) ?></dt><dd><?= esc($quiz['latestSubmission'] ?? lang('Workspace.noSubmission')) ?></dd></div>
                </dl>
                <small class="quiz-updated"><?= esc(lang('Workspace.updated')) ?> <?= esc($quiz['updatedAt'] ?? '—') ?></small>
            </article>
        <?php endforeach ?>
    </div>
    <?php if ($library['pagination']['pageCount'] > 1) : ?>
        <?php $current = $library['pagination']['page']; $last = $library['pagination']['pageCount']; $pages = array_values(array_unique(array_filter([1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $last], static fn (int $page): bool => $page >= 1 && $page <= $last))); sort($pages); ?>
        <nav class="pagination" aria-label="<?= esc(lang('Workspace.pagination'), 'attr') ?>">
            <?php $previous = 0; foreach ($pages as $page) : ?>
                <?php if ($previous !== 0 && $page > $previous + 1) : ?><span aria-hidden="true">…</span><?php endif ?>
                <a class="<?= $page === $current ? 'active' : '' ?>" <?= $page === $current ? 'aria-current="page"' : '' ?> aria-label="<?= esc(lang('Workspace.page', [$page, $last]), 'attr') ?>" href="<?= esc(site_url('dashboard') . '?' . http_build_query(array_replace($library['filters'], ['page' => $page])), 'attr') ?>"><?= $page ?></a>
            <?php $previous = $page; endforeach ?>
        </nav>
    <?php endif ?>
</section>
