<?php
$teacher = $profile['teacher'];
$pagination = $profile['pagination'];
$parts = preg_split('/\s+/u', trim((string) $teacher['displayName'])) ?: [];
$initials = '';
foreach (array_slice($parts, 0, 2) as $part) {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$pageUrl = static fn (int $page): string => site_url('user/' . $teacher['username']) . ($page > 1 ? '?page=' . $page : '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="theme-color" content="#4f46e5">
    <title><?= esc($title) ?></title>
    <meta name="description" content="<?= esc(mb_substr($teacher['bio'] !== '' ? $teacher['bio'] : $teacher['displayName'] . ' quizzes on 123test', 0, 160), 'attr') ?>">
    <link rel="icon" href="<?= esc(base_url('favicon.ico'), 'attr') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('css/public-profile.css') . '?v=' . filemtime(FCPATH . 'css/public-profile.css'), 'attr') ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="profile-header">
    <div class="container header-inner">
        <a class="brand" href="<?= esc(site_url('/'), 'attr') ?>" aria-label="123test home">
            <svg aria-hidden="true" width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="36" height="36" rx="12" fill="#a5b4fc"></rect>
                <rect x="7" y="7" width="10" height="10" rx="4" fill="#4338ca"></rect>
                <rect x="19" y="7" width="10" height="10" rx="4" fill="#4338ca"></rect>
                <rect x="7" y="19" width="10" height="10" rx="4" fill="#4338ca"></rect>
                <rect x="19" y="19" width="10" height="10" rx="4" fill="#4338ca"></rect>
            </svg>
            <span>123<em>test</em></span>
        </a>
    </div>
</header>

<main class="container profile-main" id="main-content">
    <section class="profile-intro" aria-labelledby="profile-name">
        <div class="profile-avatar" aria-hidden="true"><?= esc($initials ?: 'T') ?></div>
        <div>
            <p class="eyebrow">Teacher page</p>
            <h1 id="profile-name"><?= esc($teacher['displayName']) ?></h1>
            <p class="profile-username">@<?= esc($teacher['username']) ?></p>
            <?php if ($teacher['bio'] !== '') : ?><p class="profile-bio"><?= nl2br(esc($teacher['bio'])) ?></p><?php endif ?>
        </div>
    </section>

    <section aria-labelledby="profile-quizzes-title">
        <hr>
        <div class="section-heading" style="padding-top:12px; padding-bottom:12px;">
            <div>
                <h2 id="profile-quizzes-title">Active quizzes</h2>
            </div>
            <?php if ($pagination['total'] > 0) : ?>
                <span><?= esc((string) $pagination['total']) ?> quiz<?= $pagination['total'] === 1 ? '' : 'zes' ?>
             </span><?php endif ?>
        </div>

        <?php if ($profile['quizzes'] === []) : ?>
            <div class="empty-state"><span aria-hidden="true">▤</span><h3>No Active quizzes yet</h3><p>Check this page again when the teacher publishes a quiz.</p></div>
        <?php else : ?>
            <div class="quiz-grid">
                <?php foreach ($profile['quizzes'] as $quiz) : ?>
                    <article class="quiz-card">
                        <a class="quiz-cover" href="<?= esc($quiz['url'], 'attr') ?>"><img src="<?= esc($quiz['cover']['url'] ?? base_url('images/icon-quiz.png'), 'attr') ?>" alt=""></a>
                        <div class="quiz-content">
                            <h3><a href="<?= esc($quiz['url'], 'attr') ?>"><?= esc($quiz['title']) ?></a></h3>
                            <?php if ($quiz['description'] !== '') : ?><p class="quiz-description"><?= esc($quiz['description']) ?></p><?php endif ?>
                            <p class="quiz-meta"><?= esc((string) $quiz['questionCount']) ?> question<?= $quiz['questionCount'] === 1 ? '' : 's' ?> · <?= esc($quiz['timeLimit']) ?> minutes</p>
                            <a class="button" href="<?= esc($quiz['url'], 'attr') ?>">Open quiz <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <?php if ($pagination['pageCount'] > 1) : ?>
            <nav class="pagination" aria-label="Quiz pages">
                <?php if ($pagination['page'] > 1) : ?><a class="button button-secondary" rel="prev" href="<?= esc($pageUrl($pagination['page'] - 1), 'attr') ?>">Previous</a><?php endif ?>
                <span>Page <?= esc((string) $pagination['page']) ?> of <?= esc((string) $pagination['pageCount']) ?></span>
                <?php if ($pagination['page'] < $pagination['pageCount']) : ?><a class="button button-secondary" rel="next" href="<?= esc($pageUrl($pagination['page'] + 1), 'attr') ?>">Next</a><?php endif ?>
            </nav>
        <?php endif ?>
    </section>
</main>

<footer class="profile-footer"><div class="container">Built with 123test</div></footer>
</body>
</html>
