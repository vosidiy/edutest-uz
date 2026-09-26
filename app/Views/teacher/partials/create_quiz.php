<dialog class="dialog app-dialog" id="create-quiz-dialog" aria-labelledby="create-quiz-title">
    <form method="dialog" id="dismiss-create-quiz-form" data-dismiss-create novalidate hidden></form>
    <form method="dialog" data-create-form>
        <div class="dialog-heading"><div><p class="eyebrow"><?= esc(lang('EduTest.createQuiz.eyebrow')) ?></p><h2 id="create-quiz-title"><?= esc(lang('EduTest.createQuiz.title')) ?></h2></div><button class="btn btn-default btn-icon" type="submit" form="dismiss-create-quiz-form" value="cancel" formnovalidate data-close-create aria-label="<?= esc(lang('EduTest.createQuiz.close'), 'attr') ?>">×</button></div>
        <p class="dialog-copy"><?= esc(lang('EduTest.createQuiz.description')) ?></p>
        <label class="form-field"><span class="form-label"><?= esc(lang('EduTest.createQuiz.quizTitle')) ?></span><input class="form-control" name="title" maxlength="200" required autocomplete="off" placeholder="<?= esc(lang('EduTest.createQuiz.placeholder'), 'attr') ?>"></label>
        <fieldset class="mode-picker"><legend><?= esc(lang('EduTest.createQuiz.mode')) ?></legend>
            <label><input type="radio" name="mode" value="assessment" checked><span><strong><?= esc(lang('EduTest.createQuiz.assessment')) ?></strong><small><?= esc(lang('EduTest.createQuiz.assessmentHelp')) ?></small></span></label>
            <label><input type="radio" name="mode" value="practice"><span><strong><?= esc(lang('EduTest.createQuiz.practice')) ?></strong><small><?= esc(lang('EduTest.createQuiz.practiceHelp')) ?></small></span></label>
        </fieldset>
        <div class="create-cover-picker">
            <label class="form-field"><span class="form-label"><?= esc(lang('EduTest.createQuiz.cover')) ?></span><input class="form-control" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-describedby="create-cover-help"></label>
            <p id="create-cover-help" class="dialog-copy"><?= esc(lang('EduTest.createQuiz.coverHelp')) ?></p>
            <img class="create-cover-preview" data-cover-preview alt="<?= esc(lang('EduTest.createQuiz.coverPreview'), 'attr') ?>" hidden>
            <button class="btn btn-default btn-sm" type="button" data-remove-cover hidden><?= esc(lang('EduTest.createQuiz.removeCover')) ?></button>
        </div>
        <p class="form-error" data-create-error role="alert"></p>
        <p class="dialog-copy" data-created-notice hidden><?= esc(lang('EduTest.createQuiz.draftSaved')) ?></p>
        <p class="dialog-copy" data-create-progress role="status" aria-live="polite"></p>
        <div class="dialog-actions"><button class="btn btn-default" type="submit" form="dismiss-create-quiz-form" value="cancel" formnovalidate data-close-create><?= esc(lang('EduTest.createQuiz.cancel')) ?></button><button class="btn btn-default" type="button" data-continue-create hidden><?= esc(lang('EduTest.createQuiz.continueWithoutCover')) ?></button><button class="btn btn-primary" type="submit" data-create-submit><?= esc(lang('EduTest.createQuiz.create')) ?></button></div>
    </form>
    <script type="application/json" data-create-messages><?= json_encode(lang('EduTest.createQuiz'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
</dialog>
