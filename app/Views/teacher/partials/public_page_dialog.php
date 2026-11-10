<dialog class="dialog app-dialog" id="public-page-dialog" aria-labelledby="public-page-dialog-title">
    <form method="dialog" data-dismiss-public-page hidden></form>
    <form method="dialog" data-public-page-dialog-form>
        <div class="dialog-heading">
            <div><p class="eyebrow">Public teacher page</p><h2 id="public-page-dialog-title">Create my page</h2></div>
            <button class="btn btn-default btn-icon" type="submit" formnovalidate value="cancel" data-close-public-page aria-label="Close">×</button>
        </div>
        <p class="dialog-copy">Choose your unique link and introduce yourself. Your Active quizzes will appear automatically.</p>
        <label class="form-field" for="dialog_public_username">
            <span class="form-label">Username</span>
            <span class="public-url-input"><span><?= esc(site_url('user/')) ?></span><input class="form-control" id="dialog_public_username" name="username" type="text" minlength="3" maxlength="30" pattern="[a-z0-9]+(-[a-z0-9]+)*" autocomplete="off" autocapitalize="none" spellcheck="false" required value="<?= esc($user['username'] ?? '', 'attr') ?>" data-public-username></span>
        </label>
        <p class="form-hint" data-username-status aria-live="polite"></p>
        <label class="form-field" for="dialog_public_bio"><span class="form-label">Bio</span><textarea class="form-control" id="dialog_public_bio" name="bio" maxlength="1000" rows="5" placeholder="Tell students a little about yourself."><?= esc($user['bio'] ?? '') ?></textarea></label>
        <p class="form-error" data-public-page-error role="alert"></p>
        <div class="dialog-actions"><button class="btn btn-default" type="submit" formnovalidate value="cancel" data-close-public-page>Cancel</button><button class="btn btn-primary" type="submit" data-public-page-submit>Create page</button></div>
    </form>
</dialog>
