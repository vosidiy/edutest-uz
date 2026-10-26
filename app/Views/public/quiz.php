<?= $this->extend('layouts/student') ?>
<?= $this->section('content') ?>
<section id="quiz-introduction" class="card player-intro" aria-labelledby="quiz-title">
    <img class="player-cover" src="<?= esc($quiz['cover']['url'] ?? base_url('images/placeholder.jpg'), 'attr') ?>" alt="">
    <div class="card-body">
        <div class="player-eyebrow"><span><?= esc(lang('Player.ui.byTeacher')) ?> <?= esc($quiz['teacher']) ?></span></div>
        <h1 id="quiz-title"><?= esc($quiz['title']) ?></h1>
        <?php if ($quiz['description'] !== '') : ?><p class="player-description"><?= nl2br(esc($quiz['description'])) ?></p><?php endif ?>
        <dl class="player-facts">
            <div><dt><?= esc(lang('Player.ui.questions')) ?></dt><dd><?= esc((string) $quiz['questionCount']) ?></dd></div>
            <div><dt><?= esc(lang('Player.ui.timeLimit')) ?></dt><dd><?= esc($quiz['timeLimitMinutes'] . ' ' . lang('Player.ui.minutes')) ?></dd></div>
            <div><dt>Format</dt><dd>Single choice</dd></div>
        </dl>
        <details class="player-privacy">
            <summary><?= esc(lang('Player.ui.privacyDetails')) ?></summary>
            <p class="player-help"><?= esc(lang('Player.ui.assessmentNotice')) ?></p>
        </details>
        <form id="quiz-admission" class="player-admission" method="post">
            <label class="form-field"><span class="form-label"><?= esc(lang('Player.ui.name')) ?></span>
                <input class="form-control" name="name" type="text" maxlength="120" autocomplete="name" required>
            </label>
            <?php if ($quiz['askEmail']) : ?><label class="form-field"><span class="form-label"><?= esc(lang('Player.ui.email')) ?> (<?= esc(lang('Player.ui.optional')) ?>)</span>
                <input class="form-control" name="email" type="email" maxlength="254" autocomplete="email">
            </label><?php endif ?>
            <?php if ($quiz['askPhone']) : ?><label class="form-field"><span class="form-label"><?= esc(lang('Player.ui.phone')) ?> (<?= esc(lang('Player.ui.optional')) ?>)</span>
                <input class="form-control" name="phone" type="tel" maxlength="32" autocomplete="tel">
            </label><?php endif ?>
            <button type="submit" class="btn btn-primary btn-xl player-start" disabled><span data-start-label><?= esc(lang('Player.ui.start')) ?></span><span aria-hidden="true"> →</span></button>
            <noscript><p><?= esc(lang('Player.ui.javascriptRequired')) ?></p></noscript>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
