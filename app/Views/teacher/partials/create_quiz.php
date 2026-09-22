<dialog class="app-dialog" id="create-quiz-dialog" aria-labelledby="create-quiz-title">
    <form method="dialog" data-create-form>
        <div class="dialog-heading"><div><p class="eyebrow">New draft</p><h2 id="create-quiz-title">Create a quiz</h2></div><button class="icon-button" value="cancel" aria-label="Close">×</button></div>
        <p class="dialog-copy">Choose the starting mode. You can change it until the first student starts.</p>
        <label class="field"><span>Quiz title</span><input name="title" maxlength="200" required autocomplete="off" placeholder="e.g. Algebra midterm"></label>
        <fieldset class="mode-picker"><legend>Quiz mode</legend>
            <label><input type="radio" name="mode" value="assessment" checked><span><strong>Assessment</strong><small>Save student attempts and results.</small></span></label>
            <label><input type="radio" name="mode" value="practice"><span><strong>Practice</strong><small>Anonymous; only aggregate starts are stored.</small></span></label>
        </fieldset>
        <p class="form-error" data-create-error role="alert"></p>
        <div class="dialog-actions"><button class="button secondary" value="cancel">Cancel</button><button class="button primary" type="submit" value="default">Create draft</button></div>
    </form>
</dialog>
