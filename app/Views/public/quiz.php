<?= $this->extend('layouts/student') ?>
<?= $this->section('content') ?>
<section id="quiz-introduction" class="card player-intro" aria-labelledby="quiz-title">
    <?php if ($quiz['cover'] !== null) : ?><img class="player-cover" src="<?= esc($quiz['cover']['url'], 'attr') ?>" alt=""><?php endif ?>
    <div class="card-body">
        <div class="player-eyebrow"><span class="badge badge-primary-subtle"><?= esc(lang('Player.ui.' . $quiz['mode'])) ?></span><span><?= esc(lang('Player.ui.byTeacher')) ?> <?= esc($quiz['teacher']) ?></span></div>
        <h1 id="quiz-title"><?= esc($quiz['title']) ?></h1>
        <?php if ($quiz['description'] !== '') : ?><p class="player-description"><?= nl2br(esc($quiz['description'])) ?></p><?php endif ?>
        <dl class="player-facts">
            <div><dt><?= esc(lang('Player.ui.questions')) ?></dt><dd><?= esc((string) $quiz['questionCount']) ?></dd></div>
            <div><dt><?= esc(lang('Player.ui.timeLimit')) ?></dt><dd><?= $quiz['timeLimitSec'] === null ? esc(lang('Player.ui.noLimit')) : esc($quiz['timeLimitSec'] . ' ' . lang('Player.ui.seconds')) ?></dd></div>
            <div><dt><?= esc(lang('Player.ui.access')) ?></dt><dd><?= esc(lang($quiz['passcodeRequired'] ? 'Player.ui.passcode' : 'Player.ui.privateLink')) ?></dd></div>
        </dl>
        <?php foreach (['opensAt' => 'opens', 'closesAt' => 'closes'] as $field => $label) : ?>
            <?php if ($quiz[$field] !== null) : ?><p class="player-schedule"><?= esc(lang('Player.ui.' . $label)) ?> <time datetime="<?= esc($quiz[$field], 'attr') ?>"><?= esc($quiz[$field]) ?></time></p><?php endif ?>
        <?php endforeach ?>
        <?php if ($quiz['instructions'] !== '') : ?><section class="player-instructions"><h2><?= esc(lang('Player.ui.instructions')) ?></h2><p><?= nl2br(esc($quiz['instructions'])) ?></p></section><?php endif ?>
        <p class="player-help"><?= esc(lang('Player.ui.questionHint')) ?></p>
        <p class="player-help"><?= esc(lang('Player.ui.' . ($quiz['mode'] === 'practice' ? 'practiceNotice' : 'assessmentNotice'))) ?></p>
        <?php if ($quiz['cheatCheck']) : ?><p class="alert alert-warning"><?= esc(lang('Player.ui.integrityNotice')) ?></p><?php endif ?>
        <p class="player-help"><?= esc(lang('Player.ui.timingNotice')) ?></p>
        <button id="resume-quiz" type="button" class="btn btn-primary btn-lg" hidden><?= esc(lang('Player.ui.resume')) ?></button>
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
            <button type="submit" class="btn btn-primary btn-xl player-start" disabled><?= esc(lang('Player.ui.start')) ?><span aria-hidden="true"> →</span></button>
            <noscript><p><?= esc(lang('Player.ui.javascriptRequired')) ?></p></noscript>
        </form>
        <?php else : ?><p class="alert alert-warning"><?= esc(lang('Player.ui.' . $quiz['availability'])) ?></p><?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
