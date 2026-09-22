<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= esc(mb_substr($quiz['description'], 0, 155), 'attr') ?>">
    <title><?= esc($title) ?></title><link rel="stylesheet" href="/assets/css/public-quiz.css">
</head>
<body>
<header class="public-header"><a class="public-brand" href="<?= site_url('/') ?>"><span>E</span>EduTest</a><small>Quiz by <?= esc($quiz['teacher']) ?></small></header>
<main class="public-card">
    <section class="public-intro">
        <span class="mode-badge"><?= esc(ucfirst($quiz['mode'])) ?></span>
        <h1><?= esc($quiz['title']) ?></h1>
        <?php if ($quiz['description'] !== '') : ?><p><?= nl2br(esc($quiz['description'])) ?></p><?php endif ?>
        <dl><div><dt>Questions</dt><dd><?= esc((string) $quiz['questionCount']) ?></dd></div><div><dt>Time limit</dt><dd><?= $quiz['timeLimitSec'] === null ? 'None' : esc((string) ceil($quiz['timeLimitSec'] / 60)) . ' min' ?></dd></div><div><dt>Access</dt><dd><?= $quiz['passcodeRequired'] ? 'Passcode' : 'Private link' ?></dd></div></dl>
        <?php if ($quiz['opensAt'] !== null || $quiz['closesAt'] !== null) : ?><p class="schedule"><?php if ($quiz['opensAt'] !== null) : ?>Opens <time datetime="<?= esc($quiz['opensAt'], 'attr') ?>"><?= esc(date('j M Y, H:i', strtotime($quiz['opensAt']))) ?> UTC</time><?php endif ?><?php if ($quiz['opensAt'] !== null && $quiz['closesAt'] !== null) : ?> · <?php endif ?><?php if ($quiz['closesAt'] !== null) : ?>Closes <time datetime="<?= esc($quiz['closesAt'], 'attr') ?>"><?= esc(date('j M Y, H:i', strtotime($quiz['closesAt']))) ?> UTC</time><?php endif ?></p><?php endif ?>
    </section>
    <section class="public-notice">
        <h2><?= $quiz['availability'] === 'closed' ? 'This quiz is closed' : ($quiz['availability'] === 'scheduled' ? 'This quiz is scheduled' : 'Student access is coming next') ?></h2>
        <p><?= $quiz['availability'] === 'closed' ? 'The teacher is not accepting new starts.' : ($quiz['availability'] === 'scheduled' ? 'The quiz is published but its opening time has not arrived.' : 'This quiz has been published, but the student runner is not available in this development milestone yet.') ?></p>
        <?php if ($quiz['instructions'] !== '') : ?><details><summary>Quiz instructions</summary><p><?= nl2br(esc($quiz['instructions'])) ?></p></details><?php endif ?>
    </section>
</main>
</body></html>
