<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<header class="page-header">
    <div><p class="eyebrow">Quiz library</p><h1>My quizzes</h1></div>
    <button class="button primary" type="button" data-open-create>＋ New quiz</button>
</header>
<div class="content-area">
    <nav class="library-tabs" aria-label="Quiz library views">
        <a class="<?= $library['view'] === 'active' ? 'active' : '' ?>" href="<?= site_url('quizzes') ?>">Active</a>
        <a class="<?= $library['view'] === 'archived' ? 'active' : '' ?>" href="<?= site_url('quizzes/archived') ?>">Archived</a>
        <a class="<?= $library['view'] === 'trash' ? 'active' : '' ?>" href="<?= site_url('quizzes/trash') ?>">Trash</a>
    </nav>
    <form class="library-toolbar" method="get">
        <label class="search"><span class="sr-only">Search quizzes</span><input type="search" name="q" value="<?= esc($library['filters']['query'], 'attr') ?>" placeholder="Search quizzes"></label>
        <label><span class="sr-only">Status</span><select name="status"><option value="">All statuses</option><?php foreach (['draft', 'published', 'closed', 'archived'] as $status) : ?><option value="<?= $status ?>" <?= $library['filters']['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach ?></select></label>
        <label><span class="sr-only">Mode</span><select name="mode"><option value="">All modes</option><option value="assessment" <?= $library['filters']['mode'] === 'assessment' ? 'selected' : '' ?>>Assessment</option><option value="practice" <?= $library['filters']['mode'] === 'practice' ? 'selected' : '' ?>>Practice</option></select></label>
        <label><span class="sr-only">Sort order</span><select name="sort"><option value="updated_desc" <?= $library['filters']['sort'] === 'updated_desc' ? 'selected' : '' ?>>Recently updated</option><option value="created_desc" <?= $library['filters']['sort'] === 'created_desc' ? 'selected' : '' ?>>Recently created</option><option value="title_asc" <?= $library['filters']['sort'] === 'title_asc' ? 'selected' : '' ?>>Title A–Z</option></select></label>
        <button class="button secondary" type="submit">Apply</button>
    </form>

    <section class="quiz-list" aria-label="Quizzes">
        <?php if ($library['rows'] === []) : ?>
            <div class="empty-state"><span>▤</span><h2><?= $library['view'] === 'trash' ? 'Trash is empty' : ($library['view'] === 'archived' ? 'No archived quizzes' : 'No quizzes found') ?></h2><p><?= $library['view'] === 'active' ? 'Create a draft or adjust your filters.' : 'Quizzes moved here will appear in this view.' ?></p><?php if ($library['view'] === 'active') : ?><button class="button primary" type="button" data-open-create>New quiz</button><?php endif ?></div>
        <?php else : ?>
            <?php foreach ($library['rows'] as $quiz) : ?>
                <article class="quiz-row" data-quiz-id="<?= esc($quiz['publicId'], 'attr') ?>">
                    <a class="quiz-main" href="<?= $library['view'] === 'active' ? esc($quiz['editUrl'], 'attr') : '#' ?>" <?= $library['view'] === 'active' ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
                        <span class="quiz-symbol" aria-hidden="true">▤</span>
                        <span><strong><?= esc($quiz['title']) ?></strong><small><?= esc((string) $quiz['questionCount']) ?> questions · <?= esc(ucfirst($quiz['mode'])) ?><?php if ($quiz['frozen']) : ?> · Frozen<?php endif ?></small></span>
                    </a>
                    <span class="status <?= esc($quiz['status'], 'attr') ?>"><?= esc(ucfirst($quiz['status'])) ?></span>
                    <span class="quiz-count"><strong><?= esc((string) $quiz['responseCount']) ?></strong><small><?= $quiz['mode'] === 'practice' ? 'starts' : 'submissions' ?></small></span>
                    <small class="updated" data-date="<?= esc((string) $quiz['updatedAt'], 'attr') ?>"></small>
                    <div class="action-menu">
                        <button class="icon-button" type="button" data-menu-toggle aria-label="Quiz actions">•••</button>
                        <div class="menu" hidden>
                            <?php if ($library['view'] === 'trash') : ?>
                                <button data-quiz-action="restore">Restore</button>
                            <?php else : ?>
                                <?php if ($library['view'] === 'active') : ?><a href="<?= esc($quiz['editUrl'], 'attr') ?>">Edit</a><?php endif ?>
                                <button data-quiz-action="duplicate">Duplicate</button>
                                <?php if ($quiz['status'] === 'draft') : ?><button data-quiz-action="publish">Publish</button><?php endif ?>
                                <?php if ($quiz['status'] === 'published') : ?><button data-quiz-action="close">Close</button><?php endif ?>
                                <?php if ($quiz['status'] === 'closed') : ?><button data-quiz-action="reopen">Reopen</button><?php endif ?>
                                <?php if ($quiz['status'] !== 'archived') : ?><button data-quiz-action="archive">Archive</button><?php else : ?><button data-quiz-action="unarchive">Restore from archive</button><?php endif ?>
                                <?php if ($quiz['status'] !== 'published') : ?><button class="danger" data-quiz-action="trash">Move to trash</button><?php endif ?>
                            <?php endif ?>
                        </div>
                    </div>
                </article>
            <?php endforeach ?>
        <?php endif ?>
    </section>

    <?php if ($library['pagination']['pageCount'] > 1) : ?>
        <nav class="pagination" aria-label="Pagination">
            <?php for ($page = 1; $page <= $library['pagination']['pageCount']; $page++) : ?>
                <?php $params = array_filter(['q' => $library['filters']['query'], 'status' => $library['filters']['status'], 'mode' => $library['filters']['mode'], 'sort' => $library['filters']['sort'], 'page' => $page]); ?>
                <a class="<?= $page === $library['pagination']['page'] ? 'active' : '' ?>" href="?<?= esc(http_build_query($params), 'attr') ?>"><?= $page ?></a>
            <?php endfor ?>
        </nav>
    <?php endif ?>
</div>
<?= $this->include('teacher/partials/create_quiz') ?>
<?= $this->endSection() ?>
