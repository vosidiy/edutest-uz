<?= $this->extend('auth/layout') ?>

<?= $this->section('content') ?>
<?php $values = $old ?? session('old') ?? []; ?>
<h4 class="mb-3">Create new account</h4>

<form id="registerForm" action="<?= site_url('register') ?>" method="post">
    <?= csrf_field() ?>

    <div class="mb-4">
        <label class="form-label" for="display_name">Your name</label>
        <input class="form-control" id="display_name" name="display_name" type="text" maxlength="120" autocomplete="name" required value="<?= esc($values['display_name'] ?? '') ?>">
    </div>

    <div class="mb-4">
        <label class="form-label" for="email">Email address</label>
        <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= esc($values['email'] ?? '') ?>">
    </div>

    <div class="mb-4">
        <label class="form-label" for="phone">Phone </label>
        <input class="form-control" id="phone" name="phone" type="tel" maxlength="13" autocomplete="tel" placeholder="+998XXXXXXXXX" value="<?= esc($values['phone'] ?? '') ?>">
    </div>
    <hr>
    <div class="mb-4">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" name="password" type="password" maxlength="72" autocomplete="new-password" required>
    </div>

    <div class="mb-5">
        <label class="form-label" for="password_confirm">Confirm password</label>
        <input class="form-control" id="password_confirm" name="password_confirm" type="password" maxlength="72" autocomplete="new-password" required>
    </div>

    <button class="btn btn-primary btn-lg w-full" type="submit">Create account</button>
</form>

<hr>

<footer class="mt-5 text-center text-secondary">
    <p class="mb-2">Already registered?</p>
    <a class="btn btn-default w-full" href="<?= site_url('login') ?>">Sign in</a>
</footer>

<?= $this->endSection() ?>
