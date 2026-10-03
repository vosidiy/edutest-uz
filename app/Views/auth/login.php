<?= $this->extend('auth/layout') ?>

<?= $this->section('content') ?>
<?php $values = $old ?? session('old') ?? []; ?>
<h1 class="mb-5">Sign in</h1>

<form action="<?= site_url('login') ?>" method="post">
    <?= csrf_field() ?>

    <div class="mb-4">
        <label class="form-label" for="email">Email address</label>
        <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= esc($values['email'] ?? '') ?>">
    </div>

    <div class="mb-5">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" name="password" type="password" maxlength="72" autocomplete="current-password" required>
    </div>

    <button class="btn btn-primary btn-lg w-full" type="submit">Sign in</button>
</form>

<footer class="mt-5 text-center text-secondary">Need an account? <a href="<?= site_url('register') ?>">Register</a></footer>
<?= $this->endSection() ?>
