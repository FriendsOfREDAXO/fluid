<?php
/**
 * Einstieg (H1) im Kit „NativeCSS“:
 *   split     Text neben Bild (.ncss-split, stapelt sich per Container Query)
 *   centered  zentriert, Bild darunter
 *   compact   Seitenkopf ohne Bild (Unterseiten)
 *   type      großer Schriftzug, Betonung *Wort* im Markenverlauf (.ncss-text-gradient-brand), Etikett als .ncss-badge
 *   fullbleed Bild/Video vollflächig mit Text darauf (.ncss-hero)
 * Übrige Varianten (cards, scale, collage, compare) kommen aus dem Kit fluid.
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
$v = $b->variant() ?: 'split';
if (!in_array($v, ['split', 'centered', 'compact', 'type', 'fullbleed'], true)) {
    require \FriendsOfRedaxo\Fluid\Kit::get('fluid')->view('hero');

    return;
}
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$points = fluid_lines($d['points'] ?? '');
$text = trim((string) $d['text']);
$eager = ['eager' => true];
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));

$textHtml = static function (string $titleClass = 'h1') use ($b, $d, $text, $points, $eyebrow, $v): string {
    $h = '';
    if ('' !== $eyebrow || is_editing()) {
        $h .= '<p class="' . ('type' === $v ? 'ncss-badge nv-hero__badge' : 'eyebrow') . '"' . $b->edit('eyebrow') . '>' . e($eyebrow) . '</p>';
    }
    $h .= '<h1 id="' . e($b->titleId()) . '" class="' . $titleClass . ' nv-hero__title"' . $b->edit('title') . '>' . fluid_title((string) $d['title']) . '</h1>';
    if ('' !== $text || is_editing()) {
        $h .= '<p class="ncss-text-lead nv-hero__lead"' . $b->edit('text') . '>' . nl2br(e($text), false) . '</p>';
    }

    return $h . fluid_buttons($b, 'nv-hero__actions') . fluid_checks($points, 'nv-hero__points');
};

if ('fullbleed' === $v):
    $vid = !empty($d['video']) ? media((string) $d['video']) : null;
    $vid = $vid && str_starts_with((string) $vid['mime'], 'video/') ? $vid : null;
    $overlay = in_array($d['overlay'] ?? '', ['strong', 'medium', 'gradient'], true) ? $d['overlay'] : 'strong';
    $poster = !empty($d['image']) ? media((string) $d['image']) : null;
?>
<div class="ncss-hero nv-hero nv-hero--bleed nv-hero--<?= e($overlay) ?>">
  <div class="ncss-hero-media" aria-hidden="true">
    <?= !empty($d['image']) ? img((string) $d['image'], '100vw', ['eager' => true, 'alt' => '']) : '' ?>
    <?php if ($vid): ?><video class="nv-hero__video" data-bg-video muted loop playsinline preload="none"<?= $poster ? ' poster="' . e(media_url($poster, 1600)) . '"' : '' ?>><source src="<?= e(media_url($vid)) ?>" type="<?= e((string) $vid['mime']) ?>"></video><?php endif; ?>
  </div>
  <div class="ncss-hero-content ncss-hero-content--<?= 'gradient' === $overlay ? 'transparent' : 'filled' ?>">
    <?= $textHtml() ?>
    <?php if ($vid): ?><button type="button" class="ncss-btn nv-hero__pause" data-bg-video-toggle hidden aria-pressed="false"><?= icon('play-circle') ?><span data-label-pause="<?= e(lt('Hintergrundvideo anhalten')) ?>" data-label-play="<?= e(lt('Hintergrundvideo abspielen')) ?>"><?= e(lt('Hintergrundvideo anhalten')) ?></span></button><?php endif; ?>
  </div>
</div>
<?php elseif ('type' === $v): ?>
<div class="wrap nv-hero nv-hero--type">
  <div class="nv-hero__text"><?= $textHtml('nv-hero__display') ?></div>
  <?= fluid_image($d['image'] ?? null, '(min-width: 1280px) 1280px, 100vw', '21:9' === $ratio ? '21:9' : '16:9', 'nv-hero__media ncss-radius-lg', $eager) ?>
</div>
<?php elseif ('split' === $v):
    $media = fluid_image($d['image'] ?? null, '(min-width: 1080px) 600px, 100vw', $ratio, 'nv-hero__media', $eager);
?>
<div class="wrap">
  <div class="ncss-split-container">
    <div class="ncss-split nv-hero nv-hero--split<?= '' !== $media ? ' nv-hero--media' : '' ?>">
      <div class="ncss-split-content nv-hero__text"><?= $textHtml() ?></div>
      <?php if ('' !== $media): ?><div class="ncss-split-media"><?= $media ?></div><?php endif; ?>
    </div>
  </div>
</div>
<?php else:
    $media = 'compact' === $v ? '' : fluid_image($d['image'] ?? null, '(min-width: 1280px) 1280px, 100vw', '16:9', 'nv-hero__media', $eager);
?>
<div class="wrap nv-hero nv-hero--<?= e($v) ?>">
  <div class="nv-hero__text"><?= $textHtml() ?></div>
  <?= $media ?>
</div>
<?php endif;
