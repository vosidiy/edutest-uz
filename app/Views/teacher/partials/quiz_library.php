<section class="dashboard-library" aria-labelledby="quiz-library-title">
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
                            <a href="<?= esc($quiz['editUrl'], 'attr') ?>"><?= esc($quiz['title']) ?></a>
                        </h4>
                        <span class="badge status <?= esc($quiz['status'], 'attr') ?>"><?= esc(lang('Workspace.status.' . $quiz['status'])) ?></span>
                    </div>
                    <p style="font-weight:600">
                        <?= esc(lang('Workspace.questions', [$quiz['questionCount']])) ?> · Assessment
                    </p>
                    <hr class="my-2">
                    <ul class="quiz-item-card-stat text-secondary mb-2">
                        <li>
                            <?= esc(lang('Workspace.finalized')) ?>: 
                            <?= esc((string) $quiz['assessmentSubmissions']) ?>
                        </li>
                    </ul>
                    <p class="text-secondary">⏱️ <?= esc(lang('Workspace.updated')) ?> <?= esc($quiz['updatedAt'] ?? '—') ?></p>
                </div>
                <div class="quiz-card-actions pt-1">
                    <a class="btn btn-default " href="<?= esc($quiz['editUrl'], 'attr') ?>"><?= esc(lang('EduTest.builder.navigation.builder')) ?></a>
                    <?php if ($quiz['resultsUrl'] !== null) : ?><a class="btn btn-outline " href="<?= esc($quiz['resultsUrl'], 'attr') ?>"><?= esc(lang('EduTest.builder.navigation.responses')) ?></a><?php endif ?>
                    <div class="action-menu">
                        <button class="btn btn-default btn-icon" type="button" data-menu-toggle aria-expanded="false" aria-label="<?= esc(lang('Workspace.actions') . ': ' . $quiz['title'], 'attr') ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ellipsis preview-icon"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                        </button>
                        <div class="dropdown" hidden>
                            <button class="dropdown-item" type="button" data-quiz-action="duplicate"><?= esc(lang('Workspace.action.duplicate')) ?></button>
                            <button class="dropdown-item text-red" type="button" data-quiz-action="trash"><?= esc(lang('Workspace.action.trash')) ?></button>
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
