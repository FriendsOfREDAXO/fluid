<?php /** @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d */ ?>
<div class="wrap">
  <div class="card" style="padding:var(--f-s-l)">
    <h2 class="h3"<?= $b->edit('title') ?>><?= e($d['title']) ?></h2>
    <p<?= $b->edit('text', 'lines') ?>><?= nl2br(e($d['text']), false) ?></p>
    <?php if ('' !== trim((string) $d['link'])): ?><a class="btn btn--primary" <?= fluid_link_attrs($d['link']) ?>>Mehr</a><?php endif; ?>
  </div>
</div>
