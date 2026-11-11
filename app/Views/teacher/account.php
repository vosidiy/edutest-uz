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
$publicPageValues = session('publicPageOld') ?? [
    'username'   => $user['username'] ?? '',
    'bio'        => $user['bio'] ?? '',
    'publicPage' => (bool) ($user['public_page'] ?? false),
];
$publicPageErrors = session('publicPageErrors') ?? [];
?>
<div class="account-page">
    <header class="pt-5">
        <h2 class="mb-3"><?= esc(lang('Workspace.myAccount')) ?></h2>
        <a class="btn btn-default" href="<?= site_url('dashboard') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left preview-icon"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>    
        <?= esc(lang('Workspace.backDashboard')) ?></a>
    </header>

    <div class="account-forms">
        <section class="card account-card" aria-labelledby="profile-heading">
            <div class="card-body">
                <div>
                    <h4 id="profile-heading"><?= esc(lang('Workspace.accountPage.profileTitle')) ?></h4>
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

                    <div class="d-grid" style="grid-template-columns:1fr 1fr; gap:20px">
                        <label class="form-field" for="profile_email"><span class="form-label"><?= esc(lang('EduTest.email')) ?></span><input class="form-control" id="profile_email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= esc($profileValues['email'] ?? '', 'attr') ?>"></label>

                        <label class="form-field" for="profile_phone"><span class="form-label"><?= esc(lang('EduTest.phone')) ?></span><input class="form-control" id="profile_phone" name="phone" type="tel" maxlength="13" autocomplete="tel" placeholder="+998XXXXXXXXX" value="<?= esc($profileValues['phone'] ?? '', 'attr') ?>"></label>
                        
                    </div>
                    <div class="account-actions"><button class="btn btn-primary" type="submit"><?= esc(lang('Workspace.accountPage.saveProfile')) ?></button></div>
                </form>
            </div>
        </section>

        <section class="card account-card" aria-labelledby="public-page-heading">
            <div class="card-body">
                <div>
                    <h4 id="public-page-heading"><?= esc(lang('Workspace.accountPage.publicPageTitle')) ?></h4>
                    <p class="text-secondary"><?= esc(lang('Workspace.accountPage.publicPageHelp')) ?></p>
                </div>

                <?php if (session('publicPageSuccess') !== null) : ?>
                    <p class="alert alert-success" role="status"><?= esc(session('publicPageSuccess')) ?></p>
                <?php endif ?>
                <?php if ($publicPageErrors !== []) : ?>
                    <div class="alert alert-danger" role="alert"><ul><?php foreach ($publicPageErrors as $error) : ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
                <?php endif ?>

                <form id="publicPageForm" class="account-form" action="<?= site_url('account/public-page') ?>" method="post" data-public-page-form>
                    <?= csrf_field() ?>
                    <div>
                        <label class="form-field" for="public_username">
                            <span class="form-label"><?= esc(lang('Workspace.accountPage.username')) ?></span>
                            <span class="public-url-input"><span><?= esc(site_url('user/')) ?></span><input class="form-control" id="public_username" name="username" type="text" minlength="3" maxlength="30" pattern="[a-z0-9]+(-[a-z0-9]+)*" autocomplete="off" autocapitalize="none" spellcheck="false" required value="<?= esc($publicPageValues['username'] ?? '', 'attr') ?>" data-public-username></span>
                        </label>
                        <p class="form-hint" data-username-status aria-live="polite"></p>
                    </div>

                    <label class="form-field" for="public_bio"><span class="form-label"><?= esc(lang('Workspace.accountPage.bio')) ?> (about you)</span><textarea class="form-control" id="public_bio" name="bio" maxlength="1000" rows="5"><?= esc($publicPageValues['bio'] ?? '') ?></textarea></label>
                    
                    <label class="form-switch" for="public_page_enabled">
                        <input class="input-switch" id="public_page_enabled" name="publicPage" type="checkbox" value="1" role="switch" <?= ! empty($publicPageValues['publicPage']) ? 'checked' : '' ?>>
                        <span class="slider" aria-hidden="true"></span>
                        <span class="label-switch"><?= esc(lang('Workspace.accountPage.enablePublicPage')) ?></span>
                    </label>
                    <div class="account-actions"><button class="btn btn-primary" type="submit" data-public-page-submit><?= esc(lang('Workspace.accountPage.savePublicPage')) ?></button></div>
                </form>
            </div>
        </section>

        <section class="card account-card" aria-labelledby="password-heading">
            <div class="card-body">
                <div>
                    <h4 id="password-heading"><?= esc(lang('Workspace.accountPage.passwordTitle')) ?></h4>
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
