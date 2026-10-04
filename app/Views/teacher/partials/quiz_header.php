<?php if ($builderHeader ?? false) : ?>
        <header class="builder-bar">
            <div class="builder-title">
                <a class="btn btn-neutral btn-icon" href="<?= site_url('dashboard') ?>" aria-label="<?= esc(lang('Workspace.backDashboard'), 'attr') ?>">
                    <svg style="width: 24px; height: 24px;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left preview-icon"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                </a>
                <div>
                    <a href="#" class="builder-edit-title" ref="editTitle" @click="openDetails" aria-label="<?= esc(lang('EduTest.builder.workspace.editDetails'), 'attr') ?>">
                        <strong>{{ quiz.title || 'Untitled quiz' }}</strong>
                        <span aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pen-line preview-icon"><path d="M13 21h8"/><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
                        </span>
                    </a>
                    <small role="status" aria-live="polite" :class="saveState">{{ saveLabel }}
                    </small>
                </div>
            </div>
            <nav v-if="quiz.resultsAvailable && quiz.resultsUrl" class="builder-view-nav" aria-label="<?= esc(lang('Workspace.quizWorkspace'), 'attr') ?>">
                <a class="active" href="<?= esc(site_url('quizzes/' . $quiz['publicId'] . '/edit'), 'attr') ?>" aria-current="page"><?= esc(lang('EduTest.builder.navigation.builder')) ?></a>
                <a :href="quiz.resultsUrl"><?= esc(lang('EduTest.builder.navigation.responses')) ?></a>
            </nav>
            <div class="builder-actions">
                <button class="btn btn-neutral" type="button" @click="preview = !preview">{{ preview ? 'Edit' : 'Preview' }}</button>
                <button class="btn" :class="saveButton.primary ? 'btn-primary' : 'btn-default'" type="button" data-save-quiz @click="save(true)" :disabled="saveButton.disabled">{{ workspaceMessages[saveButton.label] }}</button>
                <button v-if="quiz.status === 'draft' || quiz.hasUnpublishedChanges" class="btn btn-primary" type="button" @click="lifecycle('publish')" :disabled="saving || mediaBusy || !publishReady" :aria-describedby="!publishReady ? 'publish-readiness' : null" :title="publishReadinessMessage">{{ quiz.hasPublished ? 'Publish changes' : 'Publish' }}</button>
                <button v-if="quiz.hasPublished" class="btn btn-default" type="button" @click="copyShare">Copy link</button>
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
