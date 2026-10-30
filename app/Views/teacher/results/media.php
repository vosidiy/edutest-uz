<?php if ($media !== null) : ?>
    <div class="report-media">
        <?php if ($media['type'] === 'image') : ?>
            <img src="<?= esc($media['url'], 'attr') ?>" alt="<?= esc($label, 'attr') ?>" loading="lazy">
        <?php elseif ($media['type'] === 'audio') : ?>
            <audio controls preload="metadata" src="<?= esc($media['url'], 'attr') ?>">Your browser cannot play this audio.</audio>
        <?php endif ?>
    </div>
<?php endif ?>
