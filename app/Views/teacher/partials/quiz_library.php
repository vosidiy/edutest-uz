<section class="dashboard-library" aria-labelledby="quiz-library-title">
    <div class="library-heading">
        
    <form class="library-toolbar" method="get" action="<?= site_url('dashboard') ?>">
        <label><span class="sr-only"><?= esc(lang('Workspace.statusLabel')) ?></span><select class="form-select" name="status"><option value=""><?= esc(lang('Workspace.allStatuses')) ?></option><?php foreach (['draft', 'published', 'closed', 'archived', 'trash'] as $status) : ?><option value="<?= $status ?>" <?= $library['filters']['status'] === $status ? 'selected' : '' ?>><?= esc(lang('Workspace.status.' . $status)) ?></option><?php endforeach ?></select></label>
        <label><span class="sr-only"><?= esc(lang('Workspace.modeLabel')) ?></span><select class="form-select" name="mode"><option value=""><?= esc(lang('Workspace.allModes')) ?></option><?php foreach (['assessment', 'practice'] as $mode) : ?><option value="<?= $mode ?>" <?= $library['filters']['mode'] === $mode ? 'selected' : '' ?>><?= esc(lang('Workspace.mode.' . $mode)) ?></option><?php endforeach ?></select></label>
        <label><span class="sr-only"><?= esc(lang('Workspace.sortLabel')) ?></span><select class="form-select" name="sort"><?php foreach (['updated_desc', 'created_desc', 'title_asc', 'submissions_desc'] as $sort) : ?><option value="<?= $sort ?>" <?= $library['filters']['sort'] === $sort ? 'selected' : '' ?>><?= esc(lang('Workspace.sort.' . $sort)) ?></option><?php endforeach ?></select></label>
        <button class="btn btn-default" type="submit"><?= esc(lang('Workspace.apply')) ?></button>
    </form>
    <div class="quiz-card-grid">
        <?php if ($library['rows'] === []) : ?>
            <div class="card empty-state quiz-grid-empty"><span aria-hidden="true">▤</span><h3><?= esc(lang('Workspace.emptyTitle')) ?></h3><p><?= esc(lang('Workspace.emptyBody')) ?></p><button class="btn btn-primary" type="button" data-open-create><?= esc(lang('Workspace.newQuiz')) ?></button></div>
        <?php endif ?>
        <?php foreach ($library['rows'] as $quiz) : ?>
            <article class="card quiz-card" data-quiz-id="<?= esc($quiz['publicId'], 'attr') ?>">
                <?php if ($quiz['editUrl'] !== null) : ?>
                    <a class="quiz-card-media" href="<?= esc($quiz['editUrl'], 'attr') ?>" aria-label="<?= esc('Open ' . $quiz['title'] . ' in quiz builder', 'attr') ?>">
                        <img src="placeholder.png" alt="">
                    </a>
                <?php else : ?>
                    <div class="quiz-card-media" aria-hidden="true">
                        <img src="placeholder.png" alt="">
                    </div>
                <?php endif ?>
                <div class="quiz-card-content">
                    <div class="quiz-card-heading">
                        <h3><?php if ($quiz['editUrl'] !== null || $quiz['resultsUrl'] !== null) : ?><a href="<?= esc($quiz['editUrl'] ?? $quiz['resultsUrl'], 'attr') ?>"><?= esc($quiz['title']) ?></a><?php else : ?><?= esc($quiz['title']) ?><?php endif ?></h3>
                        <span class="badge status <?= esc($quiz['deleted'] ? 'deleted' : $quiz['status'], 'attr') ?>"><?= esc(lang('Workspace.status.' . ($quiz['deleted'] ? 'deleted' : $quiz['status']))) ?></span>
                    </div>
                    <p class="quiz-card-meta"><?= esc(lang('Workspace.questions', [$quiz['questionCount']])) ?> · <?= esc(lang('Workspace.mode.' . $quiz['mode'])) ?><?= $quiz['hasPublished'] ? ' · ' . esc(lang('Workspace.hasPublishedPaper')) : '' ?></p>
                    <dl class="quiz-card-stats">
                        <div><dt><?= esc(lang('Workspace.finalized')) ?></dt><dd><?= esc((string) $quiz['assessmentSubmissions']) ?></dd></div>
                        <div><dt><?= esc(lang('Workspace.practiceStarts')) ?></dt><dd><?= esc((string) $quiz['practiceStarts']) ?></dd></div>
                    </dl>
                    <small class="quiz-updated"><?= esc(lang('Workspace.updated')) ?> <?= esc($quiz['updatedAt'] ?? '—') ?></small>
                </div>
                <div class="quiz-card-actions">
                    <?php if ($quiz['editUrl'] !== null) : ?><a class="btn btn-default " href="<?= esc($quiz['editUrl'], 'attr') ?>"><?= esc(lang('EduTest.builder.navigation.builder')) ?></a><?php endif ?>
                    <?php if ($quiz['resultsUrl'] !== null) : ?><a class="btn btn-outline " href="<?= esc($quiz['resultsUrl'], 'attr') ?>"><?= esc(lang('EduTest.builder.navigation.responses')) ?></a><?php endif ?>
                    <div class="action-menu">
                        <button class="btn btn-default btn-icon" type="button" data-menu-toggle aria-expanded="false" aria-label="<?= esc(lang('Workspace.actions') . ': ' . $quiz['title'], 'attr') ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ellipsis preview-icon"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                        </button>
                        <div class="dropdown" hidden>
                            <?php if ($quiz['deleted']) : ?>
                                <button class="dropdown-item" type="button" data-quiz-action="restore"><?= esc(lang('Workspace.action.restore')) ?></button>
                            <?php else : ?>
                                <button class="dropdown-item" type="button" data-quiz-action="duplicate"><?= esc(lang('Workspace.action.duplicate')) ?></button>
                                <?php foreach (['draft' => 'publish', 'published' => 'close', 'closed' => 'reopen'] as $status => $action) : ?>
                                    <?php if ($quiz['status'] === $status) : ?><button class="dropdown-item" type="button" data-quiz-action="<?= $action ?>"><?= esc(lang('Workspace.action.' . $action)) ?></button><?php endif ?>
                                <?php endforeach ?>
                                <?php $action = $quiz['status'] === 'archived' ? 'unarchive' : 'archive'; ?>
                                <button class="dropdown-item" type="button" data-quiz-action="<?= $action ?>"><?= esc(lang('Workspace.action.' . $action)) ?></button>
                                <?php if ($quiz['status'] !== 'published') : ?><button class="dropdown-item text-red" type="button" data-quiz-action="trash"><?= esc(lang('Workspace.action.trash')) ?></button><?php endif ?>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
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

    <br>
    <hr>
    <footer class="my-10">
        <p class="text-secondary text-center mb-5">
            123quiz.uz
        </p>
    </footer>
</section>
