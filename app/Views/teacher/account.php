<?= $this->extend('layouts/teacher') ?>

<?= $this->section('content') ?>
<?php
$profileValues = session('profileOld') ?? [
    'display_name' => $user['display_name'] ?? '',
    'email'        => $user['email'] ?? '',
    'phone'        => $user['phone'] ?? '',
];
$profileErrors = session('profileErrors') ?? [];
$passwordErrors = session('passwordErrors') ?? [];
?>
<div class="account-page">
    <header class="page-header">
        <div>
            <p class="eyebrow"><?= esc(lang('Workspace.accountPage.eyebrow')) ?></p>
            <h1><?= esc(lang('Workspace.myAccount')) ?></h1>
        </div>
        <a class="btn btn-default" href="<?= site_url('dashboard') ?>"><?= esc(lang('Workspace.backDashboard')) ?></a>
    </header>

    <div class="account-forms">
        <section class="card account-card" aria-labelledby="profile-heading">
            <div class="card-body">
                <div>
                    <h2 id="profile-heading"><?= esc(lang('Workspace.accountPage.profileTitle')) ?></h2>
                    <p class="text-secondary"><?= esc(lang('Workspace.accountPage.profileHelp')) ?></p>
                </div>

                <?php if (session('profileSuccess') !== null) : ?>
                    <p class="alert alert-success" role="status"><?= esc(session('profileSuccess')) ?></p>
                <?php endif ?>
                <?php if (session('profileError') !== null) : ?>
                    <p class="alert alert-danger" role="alert"><?= esc(session('profileError')) ?></p>
                <?php endif ?>
                <?php if ($profileErrors !== []) : ?>
                    <div class="alert alert-danger" role="alert"><ul><?php foreach ($profileErrors as $error) : ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
                <?php endif ?>

                <form id="profileForm" class="account-form" action="<?= site_url('account/profile') ?>" method="post">
                    <?= csrf_field() ?>
                    <label class="form-field" for="profile_display_name"><span class="form-label"><?= esc(lang('EduTest.displayName')) ?></span><input class="form-control" id="profile_display_name" name="display_name" type="text" maxlength="120" autocomplete="name" required value="<?= esc($profileValues['display_name'] ?? '', 'attr') ?>"></label>
                    <label class="form-field" for="profile_email"><span class="form-label"><?= esc(lang('EduTest.email')) ?></span><input class="form-control" id="profile_email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= esc($profileValues['email'] ?? '', 'attr') ?>"></label>
                    <label class="form-field" for="profile_phone"><span class="form-label"><?= esc(lang('EduTest.phone')) ?></span><input class="form-control" id="profile_phone" name="phone" type="tel" maxlength="13" autocomplete="tel" placeholder="+998XXXXXXXXX" value="<?= esc($profileValues['phone'] ?? '', 'attr') ?>"></label>
                    <div class="account-actions"><button class="btn btn-primary" type="submit"><?= esc(lang('Workspace.accountPage.saveProfile')) ?></button></div>
                </form>
            </div>
        </section>

        <section class="card account-card" aria-labelledby="password-heading">
            <div class="card-body">
                <div>
                    <h2 id="password-heading"><?= esc(lang('Workspace.accountPage.passwordTitle')) ?></h2>
                    <p class="text-secondary"><?= esc(lang('Workspace.accountPage.passwordHelp')) ?></p>
                </div>

                <?php if (session('passwordSuccess') !== null) : ?>
                    <p class="alert alert-success" role="status"><?= esc(session('passwordSuccess')) ?></p>
                <?php endif ?>
                <?php if (session('passwordError') !== null) : ?>
                    <p class="alert alert-danger" role="alert"><?= esc(session('passwordError')) ?></p>
                <?php endif ?>
                <?php if ($passwordErrors !== []) : ?>
                    <div class="alert alert-danger" role="alert"><ul><?php foreach ($passwordErrors as $error) : ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
                <?php endif ?>

                <form id="passwordForm" class="account-form" action="<?= site_url('account/password') ?>" method="post">
                    <?= csrf_field() ?>
                    <label class="form-field" for="current_password"><span class="form-label"><?= esc(lang('EduTest.currentPassword')) ?></span><input class="form-control" id="current_password" name="current_password" type="password" maxlength="72" autocomplete="current-password" required></label>
                    <label class="form-field" for="new_password"><span class="form-label"><?= esc(lang('EduTest.newPassword')) ?></span><input class="form-control" id="new_password" name="new_password" type="password" maxlength="72" autocomplete="new-password" required></label>
                    <label class="form-field" for="new_password_confirm"><span class="form-label"><?= esc(lang('EduTest.newPasswordConfirm')) ?></span><input class="form-control" id="new_password_confirm" name="new_password_confirm" type="password" maxlength="72" autocomplete="new-password" required></label>
                    <div class="account-actions"><button class="btn btn-primary" type="submit"><?= esc(lang('Workspace.accountPage.savePassword')) ?></button></div>
                </form>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('js/auth.js') . '?v=' . filemtime(FCPATH . 'js/auth.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
