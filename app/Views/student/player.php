<?= $this->extend('layouts/student') ?>
<?= $this->section('content') ?>
<noscript><p><?= esc(lang('Player.ui.missingState')) ?></p><a href="<?= site_url('q/' . $shareToken) ?>"><?= esc(lang('Player.ui.introduction')) ?></a></noscript>
<?= $this->endSection() ?>
