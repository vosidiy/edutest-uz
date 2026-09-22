<?= $this->extend('auth/layout') ?>

<?= $this->section('content') ?>
<?php $values = $old ?? session('old') ?? []; ?>
<h1>Sign in</h1>

<form action="<?= site_url('login') ?>" method="post">
    <?= csrf_field() ?>

    <label for="email">Email address</label>
    <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= esc($values['email'] ?? '') ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" maxlength="72" autocomplete="current-password" required>

    <button type="submit">Sign in</button>
</form>

<footer>Need an account? <a href="<?= site_url('register') ?>">Register</a></footer>
<?= $this->endSection() ?>
