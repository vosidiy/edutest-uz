<?php if ($builderHeader ?? false) : ?>
        <header class="builder-bar">
            <div class="builder-title"><a class="btn btn-default btn-icon" href="<?= site_url('dashboard') ?>" aria-label="<?= esc(lang('Workspace.backDashboard'), 'attr') ?>">←</a><span><button class="builder-edit-title" type="button" ref="editTitle" @click="openDetails" aria-label="<?= esc(lang('EduTest.builder.workspace.editDetails'), 'attr') ?>"><strong>{{ quiz.title || 'Untitled quiz' }}</strong><span aria-hidden="true">✎</span></button><small role="status" aria-live="polite" :class="saveState">{{ saveLabel }}</small></span></div>
            <nav v-if="quiz.resultsAvailable && quiz.resultsUrl" class="builder-view-nav" aria-label="<?= esc(lang('Workspace.quizWorkspace'), 'attr') ?>">
                <a class="active" href="<?= esc(site_url('quizzes/' . $quiz['publicId'] . '/edit'), 'attr') ?>" aria-current="page"><?= esc(lang('EduTest.builder.navigation.builder')) ?></a>
                <a :href="quiz.resultsUrl"><?= esc(lang('EduTest.builder.navigation.responses')) ?></a>
            </nav>
            <div class="builder-actions">
                <button class="btn btn-neutral" type="button" @click="preview = !preview">{{ preview ? 'Edit' : 'Preview' }}</button>
                <button class="btn" :class="saveButton.primary ? 'btn-primary' : 'btn-default'" type="button" data-save-quiz @click="save(true)" :disabled="saveButton.disabled">{{ workspaceMessages[saveButton.label] }}</button>
                <button v-if="quiz.status === 'draft'" class="btn btn-primary" type="button" @click="lifecycle('publish')" :disabled="saving || mediaBusy || !publishReady" :aria-describedby="!publishReady ? 'publish-readiness' : null" :title="publishReadinessMessage">Publish</button>
                <button v-else-if="quiz.status === 'published'" class="btn btn-default" type="button" @click="copyShare">Copy link</button>
            </div>
        </header>
<?php else : ?>
<header class="builder-bar">
    <div class="builder-title"><a class="btn btn-default btn-icon" href="<?= site_url('dashboard') ?>" aria-label="<?= esc(lang('Workspace.backDashboard'), 'attr') ?>">←</a><span><strong><?= esc($report['quiz']['title']) ?></strong><small><?= esc(lang('Workspace.mode.' . $report['quiz']['currentMode'])) ?> · <?= esc(lang('Workspace.status.' . $report['quiz']['status'])) ?></small></span></div>
    <nav class="builder-view-nav" aria-label="<?= esc(lang('Workspace.quizWorkspace'), 'attr') ?>">
        <?php if ($report['quiz']['builderUrl'] !== null) : ?>
            <a href="<?= esc($report['quiz']['builderUrl'], 'attr') ?>"><?= esc(lang('EduTest.builder.navigation.builder')) ?></a>
        <?php else : ?>
            <span aria-disabled="true" aria-describedby="builder-unavailable"><?= esc(lang('EduTest.builder.navigation.builder')) ?></span>
        <?php endif ?>
        <a class="active" href="<?= esc(site_url('results/quizzes/' . $report['quiz']['publicId']), 'attr') ?>" aria-current="page"><?= esc(lang('EduTest.builder.navigation.responses')) ?></a>
    </nav>
    <div class="builder-actions">
        <?php if (isset($report['exportUrl'])) : ?>
            <a class="btn btn-outline" href="<?= esc($report['exportUrl'], 'attr') ?>">↓ <?= esc(lang('Results.exportCsv')) ?></a>
        <?php else : ?>
            <a class="btn btn-default" href="<?= esc($report['quiz']['url'], 'attr') ?>">← <?= esc(lang('Workspace.backResponses')) ?></a>
        <?php endif ?>
    </div>
</header>
<?php if ($report['quiz']['builderUrl'] === null) : ?>
    <div id="builder-unavailable" class="alert alert-warning workspace-restore-notice"><?= esc(lang('Workspace.builderUnavailable')) ?> <a href="<?= esc($report['quiz']['restoreUrl'], 'attr') ?>"><?= esc(lang('Workspace.restoreQuiz')) ?></a></div>
<?php endif ?>
<?php endif ?>
