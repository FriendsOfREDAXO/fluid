<?php
/**
 * Video (YouTube/Vimeo mit Zwei-Klick-Lösung, MP4 aus dem Medienpool): wide · text (Text daneben) · cinema (randlos, dunkel).
 * Aus KLXM Studio; Einbettung: fluid_video_embed() (functions.php), Skript js/embed.js (lädt den Player erst nach Klick).
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['wide', 'text', 'cinema'], true) ? $b->variant() : 'wide';
$embed = fluid_video_embed((string) $d['video_url'], (string) $d['video_file'], (string) $d['poster'], (string) ($d['ratio'] ?: '16-9'), strip_emphasis(trim((string) $d['title'])),
    (array) ($d['tracks'] ?? []), !empty($d['cc_on']), !empty($d['transcript']));
$fig = '<figure class="video">' . $embed
    . ((trim((string) $d['caption']) !== '' || is_editing()) ? '<figcaption class="video__cap"' . $b->edit('caption') . '>' . fluid_title((string) $d['caption']) . '</figcaption>' : '')
    . '</figure>';
?>
<?php if ($v === 'text'): ?>
<div class="wrap video-split">
  <div class="video-split__text">
    <?= fluid_head($b) ?>
    <?php if (trim(strip_tags((string) $d['text'])) !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif; ?>
  </div>
  <?= $fig ?>
</div>
<?php else: ?>
<div class="wrap<?= $v === 'cinema' ? ' video-cinema' : '' ?>">
  <?= fluid_head($b, $v === 'cinema' ? 'sec-head--center' : '') ?>
  <?= $fig ?>
</div>
<?php endif;
