<?= $this->extend('auth/layout') ?>

<?= $this->section('content') ?>
<?php $values = $old ?? session('old') ?? []; ?>
<h4 class="mb-5">Sign in</h4>

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

<hr>

<footer class="mt-5 text-center text-secondary">
    <p class="mb-2">Are you new? </p>
    <a class="btn w-full btn-default" href="<?= site_url('register') ?>">Register</a>
</footer>
<?= $this->endSection() ?>
