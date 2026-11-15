<section class="dashboard-library" aria-labelledby="quiz-library-title">
    <div class="quiz-card-grid">
        <?php if ($library['rows'] === []) : ?>
            <div class="card empty-state quiz-grid-empty">
               <div>
                    <span class="icon-start">
                        <svg width="48" height="48" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" color="currentColor" viewBox="0 0 24 24"><path fill="currentColor" d="m11.5 1.3 1.6.1.3.1 1.2 1 4.8 4.8q.8.8 1.1 1.5t.3 1.9V14q0 2.8-.2 4.5a5 5 0 0 1-1.2 2.9q-1.1 1-3 1.2-1.5.2-4.4.1l-4.5-.1a5 5 0 0 1-2.9-1.2q-1-1.1-1.2-3-.2-1.5-.1-4.4V5.5Q3.6 4 4.4 3l.7-.7q1-.8 2.6-1zm0 1.5H7.8q-1.4.3-1.8.7l-.5.5q-.5.5-.6 1.8l-.2 3.7V14q0 2.9.2 4.3t.8 2 2 .8l4.3.1h4.3q1.4-.3 2-.9.6-.5.8-2l.1-4.3V9.7h-3.6q-1.3-.2-2.3-1-.8-1-1-2.3V2.8zM10 17.3a.8.8 0 0 1 0 1.4H8a.8.8 0 0 1 0-1.4zm6 0a.8.8 0 0 1 0 1.4h-2a.8.8 0 0 1 0-1.4zm-6-4a.8.8 0 0 1 0 1.4H8a.8.8 0 0 1 0-1.4zm6 0a.8.8 0 0 1 0 1.4h-2a.8.8 0 0 1 0-1.4zm-2.2-9.5v2.4q.2 1 .6 1.4t1.4.5l2.4.1z"/></svg>
                    </span>
                    <h4><?= esc(lang('Workspace.emptyTitle')) ?></h4>
                    <p><?= esc(lang('Workspace.emptyBody')) ?></p>
                    <button class="btn btn-primary" type="button" data-open-create>Create new quiz</button>
               </div>
            </div>
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
