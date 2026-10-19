<?php
/**
 * Text + Bild im Kit „NativeCSS“ (.ncss-split: nebeneinander, stapelt sich per Container Query – Bild links bzw. rechts
 * allein über die Reihenfolge im Markup). auto wechselt die Seite in einer Folge von „Text + Bild“-Blöcken wie bei fluid.
 * „split“ (randlos 50/50) und „overlap“ (Karte über großem Bild) kommen aus dem Kit fluid.
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
$v = $b->variant() ?: 'auto';
if (!in_array($v, ['auto', 'right', 'left'], true)) {
    require \FriendsOfRedaxo\Fluid\Kit::get('fluid')->view('media_text');

    return;
}
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';
$side = $v;
if ('auto' === $v) {
    $n = 0;
    for ($p = $b->prev; $p && 'media_text' === $p->type && ($p->data['variant'] ?? 'auto') === $v; $p = $p->prev) {
        ++$n;
    }
    $side = $n % 2 ? 'left' : 'right';
}
$list = fluid_lines($d['list'] ?? '');
$body = fluid_head($b, 'nv-mt__head')
    . ('' !== trim(strip_tags((string) $d['text'])) || is_editing() ? '<div class="prose"' . $b->edit('text', 'rich') . '>' . rich((string) $d['text']) . '</div>' : '')
    . fluid_checks($list)
    . fluid_buttons($b);
$pic = fluid_image($d['image'] ?? null, '(min-width: 1080px) 600px, 100vw', $ratio, 'ncss-radius-lg');
$media = '' !== $pic ? '<div class="ncss-split-media">' . $pic . '</div>' : '';
$split = in_array($d['split'] ?? 'even', ['text', 'media', 'mediaxl'], true) ? ' nv-mt--s-' . $d['split'] : '';
?>
<div class="wrap">
  <div class="ncss-split-container">
    <div class="ncss-split nv-mt nv-mt--<?= e($side) ?><?= e($split) ?>">
      <?= 'left' === $side ? $media : '' ?>
      <div class="ncss-split-content"><?= $body ?></div>
      <?= 'right' === $side ? $media : '' ?>
    </div>
  </div>
</div>
