<section class="dashboard-library" aria-labelledby="quiz-library-title">
  
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
            <?php $coverUrl = $quiz['cover']['url'] ?? null; ?>
            <article class="card quiz-card" data-quiz-id="<?= esc($quiz['publicId'], 'attr') ?>">

                <a class="quiz-card-media" href="<?= esc($quiz['editUrl'], 'attr') ?>">
                    <img src="<?= esc($coverUrl ?? base_url('images/icon-quiz.png'), 'attr') ?>" alt="">
                </a>
               
                <div class="quiz-card-content">
                    <div class="quiz-card-heading">
                        <h4>
                            <?php if ($quiz['editUrl'] !== null || $quiz['resultsUrl'] !== null) : ?>
                                <a href="<?= esc($quiz['editUrl'] ?? $quiz['resultsUrl'], 'attr') ?>"><?= esc($quiz['title']) ?>
                                </a>
                            
                                <?php else : ?><?= esc($quiz['title']) ?><?php endif ?>
                        </h4>
                        <span class="badge status <?= esc($quiz['deleted'] ? 'deleted' : $quiz['status'], 'attr') ?>"><?= esc(lang('Workspace.status.' . ($quiz['deleted'] ? 'deleted' : $quiz['status']))) ?></span>
                    </div>
                    <p style="font-weight:600">
                        <?= esc(lang('Workspace.questions', [$quiz['questionCount']])) ?> · <?= esc(lang('Workspace.mode.' . $quiz['mode'])) ?><?= $quiz['hasPublished'] ? ' • ' . esc(lang('Workspace.hasPublishedPaper')) : '' ?>
                    </p>
                    <hr class="my-2">
                    <ul class="quiz-item-card-stat text-secondary mb-2">
                        <li>
                            <?= esc(lang('Workspace.finalized')) ?>: 
                            <?= esc((string) $quiz['assessmentSubmissions']) ?>
                        </li>
                        <li>•</li>
                        <li>
                            <?= esc(lang('Workspace.practiceStarts')) ?></b>: 
                            <?= esc((string) $quiz['practiceStarts']) ?>
                        </li>
                    </ul>
                    <p class="text-secondary">⏱️ <?= esc(lang('Workspace.updated')) ?> <?= esc($quiz['updatedAt'] ?? '—') ?></p>
                </div>
                <div class="quiz-card-actions pt-1">
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

    <?php if ($paginationNavigation['pageCount'] > 1) : ?>
        <nav class="pagination" aria-label="<?= esc(lang('Workspace.pagination'), 'attr') ?>">
            <?php if ($paginationNavigation['previousUrl'] !== null) : ?>
                <a class="btn btn-default" href="<?= esc($paginationNavigation['previousUrl'], 'attr') ?>" rel="prev"><?= esc(lang('Pager.previous')) ?></a>
            <?php endif ?>
            <?php if ($paginationNavigation['nextUrl'] !== null) : ?>
                <a  class="btn btn-default" href="<?= esc($paginationNavigation['nextUrl'], 'attr') ?>" rel="next"><?= esc(lang('Pager.next')) ?></a>
            <?php endif ?>
        </nav>
    <?php endif ?>

    <br>
    <hr>
    <footer class="my-10">
        <p class="text-secondary text-center mb-5">
            123test.uz
        </p>
    </footer>
</section>
