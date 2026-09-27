<?php if ($media !== null) : ?>
    <div class="report-media">
        <?php if ($media['type'] === 'image') : ?>
            <img src="<?= esc($media['url'], 'attr') ?>" alt="<?= esc($label, 'attr') ?>" loading="lazy">
        <?php elseif ($media['type'] === 'audio') : ?>
            <audio controls preload="metadata" src="<?= esc($media['url'], 'attr') ?>">Your browser cannot play this audio.</audio>
        <?php elseif ($media['type'] === 'video' && ! empty($media['embedUrl']) && $media['embedUrl'] !== $media['url']) : ?>
            <div class="report-video"><iframe src="<?= esc($media['embedUrl'], 'attr') ?>" title="<?= esc($label, 'attr') ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen; encrypted-media" allowfullscreen></iframe></div>
        <?php elseif ($media['type'] === 'video') : ?>
            <video controls preload="metadata" src="<?= esc($media['url'], 'attr') ?>">Your browser cannot play this video.</video>
        <?php endif ?>
    </div>
<?php endif ?>
