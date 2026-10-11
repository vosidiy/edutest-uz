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
            <div><dt><?= esc(lang('Player.ui.timeLimit')) ?></dt><dd><?= $quiz['timeLimitMinutes'] === null ? esc(lang('Player.ui.noLimit')) : esc($quiz['timeLimitMinutes'] . ' ' . lang('Player.ui.minutes')) ?></dd></div>
            <div><dt><?= esc(lang('Player.ui.quizType')) ?></dt><dd><?= esc(lang('Player.ui.' . $quiz['mode'])) ?></dd></div>
        </dl>
        <?php foreach (['opensAt' => 'opens', 'closesAt' => 'closes'] as $field => $label) : ?>
            <?php if ($quiz[$field] !== null) : ?><p class="player-schedule"><?= esc(lang('Player.ui.' . $label)) ?> <time datetime="<?= esc($quiz[$field], 'attr') ?>"><?= esc($quiz[$field]) ?></time></p><?php endif ?>
        <?php endforeach ?>
        <?php if ($quiz['mode'] === 'assessment') : ?>
        <details class="player-privacy">
            <summary><?= esc(lang('Player.ui.privacyDetails')) ?></summary>
            <p class="player-help"><?= esc(lang('Player.ui.assessmentNotice')) ?></p>
        <?php if ($quiz['cheatCheck']) : ?>
            <div class="alert alert-warning player-integrity-notice">
                <p><?= esc(lang('Player.ui.integrityNotice')) ?></p>
                <div class="player-fullscreen-control">
                    <button type="button" class="btn btn-default" data-fullscreen-button><?= esc(lang('Player.ui.enableFullscreen')) ?></button>
                    <span data-fullscreen-status role="status"><?= esc(lang('Player.ui.fullscreenRecommended')) ?></span>
                </div>
            </div>
        <?php endif ?>
        </details>
        <?php endif ?>
        <?php if ($quiz['availability'] === 'available') : ?>
        <form id="quiz-admission" class="player-admission" method="post">
            <?php if ($quiz['mode'] === 'assessment') : ?>
                <?php foreach (['name' => 'required', 'email' => $quiz['emailMode'], 'phone' => $quiz['phoneMode']] as $field => $mode) : ?>
                    <?php if ($mode !== 'hidden') : ?><label class="form-field"><span class="form-label"><?= esc(lang('Player.ui.' . $field)) ?><?= $mode === 'optional' ? ' (' . esc(lang('Player.ui.optional')) . ')' : '' ?></span>
                        <input class="form-control" name="<?= esc($field, 'attr') ?>" type="<?= $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') ?>" maxlength="<?= $field === 'name' ? 120 : ($field === 'email' ? 254 : 32) ?>" autocomplete="<?= $field === 'phone' ? 'tel' : $field ?>" <?= $mode === 'required' ? 'required' : '' ?>>
                    </label><?php endif ?>
                <?php endforeach ?>
                <?php if ($quiz['passcodeRequired']) : ?><label class="form-field"><span class="form-label"><?= esc(lang('Player.ui.passcode')) ?></span><input class="form-control" type="password" name="passcode" autocomplete="off" maxlength="72" required></label><?php endif ?>
            <?php endif ?>
            <button type="submit" class="btn btn-primary btn-xl player-start" disabled><span data-start-label><?= esc(lang('Player.ui.start')) ?></span><span aria-hidden="true"> →</span></button>
            <noscript><p><?= esc(lang('Player.ui.javascriptRequired')) ?></p></noscript>
        </form>
        <?php else : ?><p class="alert alert-warning"><?= esc(lang('Player.ui.' . $quiz['availability'])) ?></p><?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
