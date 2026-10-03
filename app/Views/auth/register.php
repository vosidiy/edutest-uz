<?= $this->extend('auth/layout') ?>

<?= $this->section('content') ?>
<?php $values = $old ?? session('old') ?? []; ?>
<h1 class="mb-2">Create a teacher account</h1>
<p class="mb-5 text-secondary">Use your email address to sign in.</p>

<form action="<?= site_url('register') ?>" method="post">
    <?= csrf_field() ?>

    <div class="mb-4">
        <label class="form-label" for="display_name">Display name</label>
        <input class="form-control" id="display_name" name="display_name" type="text" maxlength="120" autocomplete="name" required value="<?= esc($values['display_name'] ?? '') ?>">
    </div>

    <div class="mb-4">
        <label class="form-label" for="email">Email address</label>
        <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= esc($values['email'] ?? '') ?>">
    </div>

    <div class="mb-4">
        <label class="form-label" for="phone">Phone <span class="text-secondary">(optional)</span></label>
        <input class="form-control" id="phone" name="phone" type="tel" maxlength="32" autocomplete="tel" value="<?= esc($values['phone'] ?? '') ?>">
    </div>

    <div class="mb-4">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" name="password" type="password" minlength="6" maxlength="72" autocomplete="new-password" required>
    </div>

    <div class="mb-5">
        <label class="form-label" for="password_confirm">Confirm password</label>
        <input class="form-control" id="password_confirm" name="password_confirm" type="password" minlength="6" maxlength="72" autocomplete="new-password" required>
    </div>

    <button class="btn btn-primary btn-lg w-full" type="submit">Create account</button>
</form>

<footer class="mt-5 text-center text-secondary">Already registered? <a href="<?= site_url('login') ?>">Sign in</a></footer>
<?= $this->endSection() ?>
