<?= $this->extend('auth/layout') ?>

<?= $this->section('content') ?>
<?php $values = $old ?? session('old') ?? []; ?>
<h1>Create a teacher account</h1>
<p class="hint">Use your email address to sign in.</p>

<form action="<?= site_url('register') ?>" method="post">
    <?= csrf_field() ?>

    <label for="display_name">Display name</label>
    <input id="display_name" name="display_name" type="text" maxlength="120" autocomplete="name" required value="<?= esc($values['display_name'] ?? '') ?>">

    <label for="email">Email address</label>
    <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= esc($values['email'] ?? '') ?>">

    <label for="phone">Phone <span class="hint">(optional)</span></label>
    <input id="phone" name="phone" type="tel" maxlength="32" autocomplete="tel" value="<?= esc($values['phone'] ?? '') ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" minlength="6" maxlength="72" autocomplete="new-password" required>

    <label for="password_confirm">Confirm password</label>
    <input id="password_confirm" name="password_confirm" type="password" minlength="6" maxlength="72" autocomplete="new-password" required>

    <button type="submit">Create account</button>
</form>

<footer>Already registered? <a href="<?= site_url('login') ?>">Sign in</a></footer>
<?= $this->endSection() ?>
