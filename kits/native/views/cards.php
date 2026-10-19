<?php
/**
 * Karten im Kit „NativeCSS“ (.ncss-card mit Rahmen, Radius, Schatten; ganze Karte klickbar über .ncss-card-link):
 *   image       Bild oben
 *   overlay     Text auf dem Bild
 *   horizontal  quer, sobald die Karte breit genug ist (.ncss-card--horizontal im .ncss-card-container)
 * „reel“ (wischbares Band) kommt aus dem Kit fluid.
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
$v = $b->variant() ?: 'image';
if (!in_array($v, ['image', 'overlay', 'horizontal'], true)) {
    require \FriendsOfRedaxo\Fluid\Kit::get('fluid')->view('cards');

    return;
}
$ratio = (string) ($d['ratio'] ?? '') ?: '3:2';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => '' !== trim((string) ($i['title'] ?? '')) || is_editing()));
$tag = fluid_htag($d);
$min = ['s' => '14rem', 'm' => '18rem', 'l' => '24rem'][$d['size'] ?? 'm'] ?? '18rem';
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ul class="ncss-grid nv-cards nv-cards--<?= e($v) ?>" role="list" style="--ncss-grid-min: <?= e($min) ?>">
    <?php foreach ($items as $i => $it):
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $pic = fluid_image(!empty($it['image']) ? (string) $it['image'] : null, 'horizontal' === $v ? '(min-width: 1080px) 320px, 100vw' : '(min-width: 1080px) 420px, (min-width: 640px) 50vw, 90vw', $ratio);
        $card = 'ncss-card ncss-border ncss-radius-lg ncss-shadow-sm' . ('' !== $link ? ' ncss-card--linked ncss-shadow-hover-md' : '') . ('horizontal' === $v ? ' ncss-card--horizontal' : '') . ('overlay' === $v ? ' nv-card--overlay' : '');
    ?>
    <li class="<?= 'horizontal' === $v ? 'ncss-card-container' : 'nv-cards__item' ?>" data-reveal><?= $b->targetEdit($link, (string) ($it['title'] ?? '')) ?>
      <article class="<?= e($card) ?>">
        <?php if ('' !== $pic): ?><div class="ncss-card-media"><?= $pic ?></div><?php endif; ?>
        <div class="ncss-card-body">
          <?php if ('' !== trim((string) ($it['eyebrow'] ?? ''))): ?><p class="nv-card__eyebrow"<?= $b->edit("items.$i.eyebrow") ?>><?= e($it['eyebrow']) ?></p><?php endif; ?>
          <<?= $tag ?> class="nv-card__title"><?php if ('' !== $link && !is_editing()): ?><a class="ncss-card-link" <?= fluid_link_attrs($link) ?>><?= e($it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= e((string) ($it['title'] ?? '')) ?></span><?php endif; ?></<?= $tag ?>>
          <?php if ('' !== trim((string) ($it['text'] ?? ''))): ?><p class="nv-card__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
        </div>
        <?php if ('' !== $link && '' !== $label): ?><div class="ncss-card-footer"><span class="nv-more" aria-hidden="true"><?= e($label) ?><?= icon('arrow-right') ?></span></div><?php endif; ?>
      </article>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if ('' !== trim((string) ($d['more_label'] ?? '')) && '' !== trim((string) ($d['more_link'] ?? ''))): ?>
  <p class="more-row"><a class="ncss-btn ncss-btn--secondary" <?= fluid_link_attrs($d['more_link']) ?>><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span></a></p>
  <?php endif; ?>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Karten – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
