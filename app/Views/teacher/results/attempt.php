<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<?= $this->include('teacher/partials/quiz_header') ?>
<div class="container">
    <header class="card mt-5 mb-5 p-4 page-header-attempt-detail">
        <div style="flex-grow:1">
            <p class="eyebrow"><a href="<?= site_url('dashboard') ?>"><?= esc(lang('Workspace.dashboardTitle')) ?></a> <span aria-hidden="true">/</span> <a href="<?= esc($report['quiz']['url'], 'attr') ?>"><?= esc($report['quiz']['title']) ?></a></p>

            <div class="results-title-line">
                <h4 class="mb-2">
                    <?= esc(lang('Results.attemptTitle')) ?>: 
                    <?= esc($report['attempt']['name']) ?>
                </h4>
                <span class="badge attempt-status <?= esc($report['attempt']['status'], 'attr') ?>"><?= esc(ucwords(str_replace('_', ' ', $report['attempt']['status']))) ?></span>
            </div>
        </div>
        <div>
            <article class="card p-3" style="min-width:200px; background:var(--yellow-100)">
                <?php if ($report['attempt']['percent'] === null) : ?><strong>—</strong><small>Not finalized.</small>
                <?php else : ?><strong class="text-xl"><?= esc($report['attempt']['percent']) ?>%</strong><small><?= esc($report['attempt']['score']) ?> / <?= esc($report['attempt']['maxScore']) ?> questions</small><?php endif ?>
            </article>
        </div>
    </header>

    <div class="content-area results-workspace attempt-review">
        
        <?php if ($report['attempt']['status'] === 'abandoned') : ?>
            <p class="alert alert-warning">
            This is a provisional expiry result. A valid delayed final upload can replace it.</p>
            <?php endif ?>
            <?php if ($report['attempt']['lateSync']) : ?><p class="alert alert-warning"><?= esc(lang('Results.lateSyncNotice')) ?></p>
            <?php endif ?>

        <article class="card attempt-summary-card">
            <dl class="result-definition-grid">
                <div><dt>Name</dt><dd><?= esc($report['attempt']['name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= esc($report['attempt']['email'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Phone</dt><dd><?= esc($report['attempt']['phone'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Status</dt><dd><?= esc(ucwords(str_replace('_', ' ', $report['attempt']['status']))) ?></dd></div>
                <div><dt>Finish reason</dt><dd><?= esc($report['attempt']['finishReason'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Duration</dt><dd><?= esc($report['attempt']['duration'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Started</dt><dd><?= esc($report['attempt']['startedAt']['display'] ?? lang('Results.notAvailable')) ?></dd></div>
                <div><dt>Finished</dt><dd><?= esc($report['attempt']['submittedAt']['display'] ?? lang('Results.notAvailable')) ?></dd></div>
            </dl>
            <hr>
            <h6 id="connection-heading">Connection details</h6><p><?= esc(lang('Results.privacyNote')) ?></p>
            IP address: <?= esc($report['attempt']['ip'] ?? lang('Results.notAvailable')) ?>
            Browser user agent: <?= esc($report['attempt']['agent'] ?? lang('Results.notAvailable')) ?>

            <hr>
            <h6 id="policy-heading">Quiz policy</h6>
            <p>Visibility and timer settings stored at start.</p>
                <?php foreach ($report['policies'] as $policy) : ?> 
                   <?= esc($policy['label']) ?>   <?= esc($policy['value']) ?> &nbsp; | &nbsp; 
                <?php endforeach ?>
            </section>
        </article>

        

        <section class="answer-review-section" aria-labelledby="answer-review-heading">
            <h2 class="text-2xl" id="answer-review-heading">Questions and answers (<?= esc((string) count($report['questions'])) ?>)</h2>
            
            <?php foreach ($report['questions'] as $question) : ?>
                <article class="card answer-review-card">
                    <header>
                        <div>
                            <p class="eyebrow">Question <?= esc((string) $question['position']) ?> 
                            · <?= esc($question['optionsLayout']) ?></p>
                            <h5><?= nl2br(esc($question['content'])) ?></h5>
                        </div>
                        <div class="answer-statuses">
                            <span class="badge answer-result <?= esc($question['result'] ?? 'ungraded', 'attr') ?>"><?= esc($question['result'] === null ? lang('Results.ungraded') : ucfirst($question['result'])) ?>
                            </span>
                        </div>
                    </header>
                    <?= $this->setData(['media' => $question['media'], 'label' => 'Question media'])->include('teacher/results/media') ?>
                    <ul class="answer-options" aria-label="Answer choices">
                        <?php foreach ($question['options'] as $option) : ?>
                            <li class="<?= $option['selected'] ? 'selected' : '' ?> <?= $option['correct'] ? 'correct' : '' ?>">
                                <span class="answer-option-marker" aria-hidden="true"><?= $option['selected'] ? '●' : '○' ?></span>
                                <div><span class="answer-option-text"><strong><?= esc($option['label']) ?>)</strong> <?= nl2br(esc($option['content'])) ?></span><?= $this->setData(['media' => $option['media'], 'label' => 'Answer option media'])->include('teacher/results/media') ?></div>
                                <span class="answer-option-label"><?= $option['selected'] && $option['correct'] ? 'Selected · Correct' : ($option['selected'] ? 'Selected' : ($option['correct'] ? 'Correct answer' : '')) ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                    <?php if ($question['explanation'] !== null) : ?><div class="answer-explanation"><strong>Explanation</strong><p><?= nl2br(esc($question['explanation'])) ?></p></div><?php endif ?>
                    <footer class="p-3" style="background:var(--neutral-100)">
                        <span>State: <?= esc(ucfirst($question['status'])) ?></span><?php if ($question['answeredAt'] !== null) : ?><span>Answered at: <?= esc($question['answeredAt']['display']) ?></span><?php endif ?>
                    </footer>
                </article>
            <?php endforeach ?>
            <br>
            <hr>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
