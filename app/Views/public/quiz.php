<?= $this->extend('layouts/student') ?>
<?= $this->section('content') ?>
<section id="quiz-introduction" class="card player-intro" aria-labelledby="quiz-title">
    <img class="player-cover" src="<?= esc($quiz['cover']['url'] ?? base_url('images/placeholder.png'), 'attr') ?>" alt="Quiz screenshot illustration">
    <div class="card-body">
        <p><?= esc(lang('Player.ui.byTeacher')) ?> <?= esc($quiz['teacher']) ?></p>
        <h1 class="title-intro-quiz" id="quiz-title"><?= esc($quiz['title']) ?></h1>
        <?php if ($quiz['description'] !== '') : ?><p class="player-description"><?= nl2br(esc($quiz['description'])) ?></p><?php endif ?>
        <hr>
        <p>
            <strong><?= esc(lang('Player.ui.questions')) ?></strong> : 
            <?= esc((string) $quiz['questionCount']) ?> </p>
        <p>
            <strong><?= esc(lang('Player.ui.timeLimit')) ?></strong> : 
            <?= esc($quiz['timeLimitMinutes'] . ' ' . lang('Player.ui.minutes')) ?> </p>
        
    
        <form id="quiz-admission" class="player-admission" method="post">
            <label class="form-field">
                <strong class="form-label"><?= esc(lang('Player.ui.name')) ?></strong>
                <input class="form-control" placeholder="Type here" name="name" type="text" maxlength="120" autofocus autocomplete="name" required>
            </label>
            <?php if ($quiz['askEmail']) : ?>
            <label class="form-field">
                <strong class="form-label"><?= esc(lang('Player.ui.email')) ?> (<?= esc(lang('Player.ui.optional')) ?>)</strong>
                <input class="form-control" name="email" type="email" maxlength="254" autocomplete="email">
            </label><?php endif ?>
            <?php if ($quiz['askPhone']) : ?>
            <label class="form-field">
                    <strong class="form-label"><?= esc(lang('Player.ui.phone')) ?> (<?= esc(lang('Player.ui.optional')) ?>)</strong>
                    <input class="form-control"  name="phone" type="tel" maxlength="32" autocomplete="tel">
            </label><?php endif ?>
            <button type="submit" class="btn btn-primary btn-xl player-start" disabled><span data-start-label><?= esc(lang('Player.ui.start')) ?></span><span aria-hidden="true"> →</span></button>
            <noscript><p><?= esc(lang('Player.ui.javascriptRequired')) ?></p></noscript>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
