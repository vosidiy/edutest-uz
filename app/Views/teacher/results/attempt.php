<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<?= $this->include('teacher/partials/quiz_header') ?>
<header class="page-header results-page-header">
    <div>
        <p class="eyebrow"><a href="<?= site_url('dashboard') ?>"><?= esc(lang('Workspace.dashboardTitle')) ?></a> <span aria-hidden="true">/</span> <a href="<?= esc($report['quiz']['url'], 'attr') ?>"><?= esc($report['quiz']['title']) ?></a></p>
        <div class="results-title-line"><h1><?= esc(lang('Results.attemptTitle')) ?>: <?= esc($report['attempt']['name']) ?></h1><span class="badge attempt-status <?= esc($report['attempt']['status'], 'attr') ?>"><?= esc(ucwords(str_replace('_', ' ', $report['attempt']['status']))) ?></span></div>
        <p><?= esc(lang('Results.paperRevision')) ?> <?= esc((string) $report['attempt']['paperRevision']) ?> · <?= esc(lang('Results.receivedMode')) ?>: <?= esc(ucfirst($report['paper']['mode'])) ?> · <?= esc(lang('Results.currentMode')) ?>: <?= esc(ucfirst($report['quiz']['currentMode'])) ?>. Times use <?= esc($report['timezone']) ?>.</p>
    </div>
</header>

<div class="content-area results-workspace attempt-review">
    <section class="attempt-summary-grid" aria-label="Attempt summary">
        <article class="card attempt-score-card">
            <span>Official score</span>
            <?php if ($report['attempt']['percent'] === null) : ?>
                <strong>—</strong><small><?= esc(lang('Results.ungraded')) ?> while this attempt remains in progress.</small>
            <?php else : ?>
                <strong><?= esc($report['attempt']['percent']) ?>%</strong><small><?= esc($report['attempt']['score']) ?> / <?= esc($report['attempt']['maxScore']) ?> questions</small>
            <?php endif ?>
        </article>
        <article class="card attempt-summary-card">
            <h2>Identity and lifecycle</h2>
            <dl class="result-definition-grid">
                <div><dt>Name</dt><dd><?= esc($report['attempt']['name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= esc($report['attempt']['email'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Phone</dt><dd><?= esc($report['attempt']['phone'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Status</dt><dd><?= esc(ucwords(str_replace('_', ' ', $report['attempt']['status']))) ?></dd></div>
                <div><dt>Finish reason</dt><dd><?= esc($report['attempt']['finishReason'] === null ? lang('Results.notAvailable') : ucwords(str_replace('_', ' ', $report['attempt']['finishReason']))) ?></dd></div>
                <div><dt>Duration</dt><dd><?= esc($report['attempt']['duration'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Started</dt><dd><?= esc($report['attempt']['startedAt']['display'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Submitted</dt><dd><?= esc($report['attempt']['submittedAt']['display'] ?? lang('Results.notAvailable')) ?></dd></div>
            </dl>
        </article>
    </section>
    <section class="card panel paper-snapshot" aria-labelledby="paper-snapshot-heading">
        <div class="panel-heading"><div><p class="eyebrow"><?= esc(lang('Results.paperRevision')) ?> <?= esc((string) $report['paper']['revision']) ?></p><h2 id="paper-snapshot-heading"><?= esc($report['paper']['title']) ?></h2><p><?= esc(lang('Results.paperSnapshotNotice')) ?></p></div></div>
        <?php if ($report['paper']['description'] !== '') : ?><p><?= nl2br(esc($report['paper']['description'])) ?></p><?php endif ?>
        <?php if ($report['paper']['instructions'] !== '') : ?><div class="answer-explanation"><strong>Instructions</strong><p><?= nl2br(esc($report['paper']['instructions'])) ?></p></div><?php endif ?>
    </section>


    <section class="card sensitive-detail" aria-labelledby="connection-heading">
        <div><h2 id="connection-heading">Connection details</h2><p><?= esc(lang('Results.privacyNote')) ?></p></div>
        <dl><div><dt>IP address</dt><dd><?= esc($report['attempt']['ip'] ?? lang('Results.notAvailable')) ?></dd></div><div><dt>Browser user agent</dt><dd class="user-agent-value"><?= esc($report['attempt']['agent'] ?? lang('Results.notAvailable')) ?></dd></div></dl>
    </section>

    <section class="card panel policy-panel" aria-labelledby="policy-heading">
        <div class="panel-heading"><div><h2 id="policy-heading">Captured policy</h2><p>Settings recorded for this attempt, independent of later teacher changes.</p></div></div>
        <dl class="policy-grid"><?php foreach ($report['policies'] as $policy) : ?><div><dt><?= esc($policy['label']) ?></dt><dd><?= esc($policy['value']) ?></dd></div><?php endforeach ?></dl>
    </section>

    <section class="answer-review-section" aria-labelledby="answer-review-heading">
        <div class="section-heading"><div><p class="eyebrow">Saved response detail</p><h2 id="answer-review-heading">Questions and answers</h2></div><span><?= esc((string) count($report['questions'])) ?> questions</span></div>
        <?php if ($report['questions'] === []) : ?>
            <div class="card empty-state"><span aria-hidden="true">?</span><h3>No question records</h3><p>This attempt does not contain saved question items.</p></div>
        <?php endif ?>
        <?php foreach ($report['questions'] as $question) : ?>
            <article class="card answer-review-card">
                <header>
                    <div><p class="eyebrow">Question <?= esc((string) $question['position']) ?> · <?= esc(ucwords(str_replace('_', ' ', $question['type']))) ?></p><h3><?= nl2br(esc($question['content'])) ?></h3></div>
                    <div class="answer-statuses"><span class="badge answer-result <?= esc($question['result'] ?? 'ungraded', 'attr') ?>"><?= esc($question['result'] === null ? lang('Results.ungraded') : ucfirst($question['result'])) ?></span></div>
                </header>

                <?= $this->setData(['media' => $question['media'], 'label' => 'Question media'])->include('teacher/results/media') ?>

                <?php if ($question['type'] === 'short_text') : ?>
                    <div class="text-answer-review">
                        <div><span>Saved answer</span><p><?= $question['answer'] === null ? '<em>' . esc(lang('Results.noAnswer')) . '</em>' : nl2br(esc($question['answer'])) ?></p></div>
                        <div><span>Accepted answer<?= count($question['correctAnswer']) === 1 ? '' : 's' ?></span><p><?= $question['correctAnswer'] === [] ? '<em>' . esc(lang('Results.notAvailable')) . '</em>' : esc(implode(' · ', $question['correctAnswer'])) ?></p></div>
                    </div>
                <?php else : ?>
                    <ul class="answer-options" aria-label="Answer choices">
                        <?php foreach ($question['options'] as $option) : ?>
                            <li class="<?= $option['selected'] ? 'selected' : '' ?> <?= $option['correct'] ? 'correct' : '' ?>">
                                <span class="answer-option-marker" aria-hidden="true"><?= $option['selected'] ? '●' : '○' ?></span>
                                <div><span class="answer-option-text"><strong><?= esc($option['label']) ?>)</strong> <?= nl2br(esc($option['content'])) ?></span><?= $this->setData(['media' => $option['media'], 'label' => 'Answer option media'])->include('teacher/results/media') ?></div>
                                <span class="answer-option-label"><?= $option['selected'] && $option['correct'] ? 'Selected · Correct' : ($option['selected'] ? 'Selected' : ($option['correct'] ? 'Correct answer' : '')) ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                    <?php if ($question['options'] === [] && $question['answer'] === []) : ?><p class="result-empty-inline"><?= esc(lang('Results.noAnswer')) ?></p><?php endif ?>
                <?php endif ?>

                <?php if ($question['explanation'] !== null) : ?><div class="answer-explanation"><strong>Explanation</strong><p><?= nl2br(esc($question['explanation'])) ?></p></div><?php endif ?>
                <footer class="answer-metadata">
                    <span>State: <?= esc(ucfirst($question['status'])) ?></span>
                    <span>Lock: <?= esc($question['lockReason'] === null ? lang('Results.notAvailable') : ucwords(str_replace('_', ' ', $question['lockReason']))) ?></span>
                    <span>Started: <?= esc($question['startedAt']['display'] ?? lang('Results.notAvailable')) ?></span>
                    <span>Locked: <?= esc($question['lockedAt']['display'] ?? lang('Results.notAvailable')) ?></span>
                    <span>Response time: <?= esc($question['responseTime'] ?? lang('Results.notAvailable')) ?></span>
                </footer>
            </article>
        <?php endforeach ?>
    </section>

    <section class="card panel integrity-panel" aria-labelledby="integrity-heading">
        <div class="panel-heading"><div><h2 id="integrity-heading">Integrity timeline</h2><p>Recorded browser observations are informational and never apply an automatic penalty.</p></div><span class="badge mode-assessment"><?= esc((string) count($report['events'])) ?> events</span></div>
        <?php if ($report['events'] === []) : ?>
            <div class="empty-state compact-empty"><p><?= esc(lang('Results.noEvents')) ?></p></div>
        <?php else : ?>
            <ol class="integrity-timeline">
                <?php foreach ($report['events'] as $event) : ?>
                    <li><span class="timeline-dot" aria-hidden="true"></span><div><div class="timeline-heading"><strong><?= esc(lang('Results.eventTypes.' . $event['type'])) ?></strong><time datetime="<?= esc($event['receivedAt']['iso'] ?? '', 'attr') ?>"><?= esc($event['receivedAt']['display'] ?? lang('Results.notAvailable')) ?></time></div><p>Occurred: <?= esc($event['happenedAt']['display'] ?? lang('Results.notAvailable')) ?><?php if ($event['durationMs'] !== null) : ?> · Duration <?= esc((string) $event['durationMs']) ?> ms<?php endif ?></p><?php if ($event['metadata'] !== []) : ?><details><summary>Event metadata</summary><pre><?= esc(json_encode($event['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></pre></details><?php endif ?></div></li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    </section>
</div>
<?= $this->endSection() ?>
