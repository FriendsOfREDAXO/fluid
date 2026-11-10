<?php
/**
 * Bildergalerie: grid (gleiches Format) · masonry (Spalten, Originalformat) · justified (bündige Zeilen). Lightbox über
 * js/media.js – ohne JavaScript führen die Bilder als Links zur großen Fassung. @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
$layout = in_array($b->variant(), ['grid', 'masonry', 'justified'], true) ? $b->variant() : 'grid';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4', '5'], true) ? (string) $d['columns'] : '3';
$ratio = (string) ($d['ratio'] ?? '4:3');
$lightbox = !empty($d['lightbox']) && !is_editing();
$items = [];
foreach (array_values((array) ($d['images'] ?? [])) as $i => $it) {
    $file = (string) ($it['image'] ?? '');
    if ('' !== $file && str_starts_with((string) \Redaxo\Core\MediaPool\Media::get($file)?->type, 'image/')) {
        $items[] = ['file' => $file, 'caption' => trim((string) ($it['caption'] ?? '')), 'i' => $i];
    }
}
$sizes = [
    '2' => '(min-width: 1280px) 600px, (min-width: 640px) 50vw, 100vw',
    '3' => '(min-width: 1280px) 400px, (min-width: 640px) 50vw, 100vw',
    '4' => '(min-width: 1280px) 300px, (min-width: 860px) 25vw, 50vw',
    '5' => '(min-width: 1280px) 240px, (min-width: 860px) 20vw, 50vw',
][$cols];
if ('justified' === $layout) {
    $sizes = '(min-width: 860px) 40vw, 100vw';
}
$r = 'r-' . str_replace(':', '-', $ratio);
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ul class="cms-gallery cms-gallery--<?= e($layout) ?> cols-<?= e($cols) ?>" role="list"<?= $lightbox ? ' data-cms-lightbox' : '' ?>>
    <?php foreach ($items as $n => $it):
        $pic = img($it['file'], $sizes, 'grid' === $layout ? ['ratio' => $ratio] : []);
        $cap = !empty($d['captions']) ? $it['caption'] : '';
        $alt = (string) (\Redaxo\Core\MediaPool\Media::get($it['file'])?->getValue('meta_alt') ?? '');
        $cls = 'cms-gallery__link' . ('grid' === $layout ? ' ' . $r : '');
    ?>
    <li class="cms-gallery__item<?= 'justified' === $layout ? ' ' . fluid_aspect_class($it['file']) : '' ?>">
      <figure class="cms-gallery__fig">
        <?php if ($lightbox): ?>
        <a class="<?= e($cls) ?>"<?= fluid_lightbox_link($it['file'], $it['caption']) ?>><?= $pic ?><span class="sr-only"><?= e(lt('Bild {n} vergrößern', ['n' => $n + 1])) ?></span></a>
        <?php else: ?>
        <div class="<?= e($cls) ?>"><?= $pic ?></div>
        <?php endif; ?>
        <?php if ('' !== $cap || (is_editing() && !empty($d['captions']))): ?><figcaption class="cms-gallery__cap"<?= $b->edit('images.' . $it['i'] . '.caption') ?>><?= e($cap) ?></figcaption><?php endif; ?>
      </figure>
    </li>
    <?php endforeach; ?>
  </ul>
  <?= $lightbox ? fluid_lightbox() : '' ?>
  <?php elseif (is_editing()): ?>
  <p class="empty-hint">Noch keine Bilder – in der Seitenleiste unter „Bilder“ hinzufügen.</p>
  <?php endif; ?>
</div>
