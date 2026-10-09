<?php
/**
 * Downloads – einzelne Dateien oder alle Dateien einer Medienkategorie (Medienpool). PDFs auf Wunsch mit „Ansehen“.
 * Aus KLXM Studio (dort mit PDF.js-Viewer und Sammlungen).
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaCategory;

$rows = [];
if (($d['source'] ?? 'manual') === 'collection' && '' !== (string) ($d['collection'] ?? '')) {
    $cat = MediaCategory::get((int) $d['collection']);
    foreach ($cat ? $cat->getMedia() : [] as $m) {
        $rows[] = ['m' => $m, 'label' => $m->title ?: $m->originalFileName, 'note' => '', 'path' => null];
    }
} else {
    foreach ($d['files'] as $i => $f) {
        $m = !empty($f['file']) ? Media::get((string) $f['file']) : null;
        if (!$m && !is_editing()) continue;
        $rows[] = ['m' => $m, 'label' => trim((string) ($f['label'] ?? '')) ?: ($m ? ($m->title ?: $m->originalFileName) : '[Datei wählen]'),
            'note' => (string) ($f['note'] ?? ''), 'path' => "files.$i.label"];
    }
}
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($rows): ?>
  <ul class="downloads" role="list">
    <?php foreach ($rows as $row): $m = $row['m']; $type = $m ? strtoupper($m->getExtension()) : '–'; $pdf = $m && 'application/pdf' === $m->type;
        $meta = $m ? $type . ' · ' . $m->getFormattedSize() : ''; ?>
    <li class="download">
      <span class="download__type" aria-hidden="true"><?= e($type) ?></span>
      <span class="download__main">
        <span class="download__name"<?= $row['path'] ? $b->edit($row['path']) : '' ?>><?= fluid_title((string) $row['label']) ?></span>
        <span class="download__meta"><?= e(implode(' · ', array_filter([$meta, $row['note']]))) ?></span>
      </span>
      <?php if ($m): ?>
      <span class="download__actions">
        <?php if ($pdf && !empty($d['show_viewer'])): ?><a class="download__view" href="<?= e($m->getUrl()) ?>" target="_blank" rel="noopener"><?= e(lt('Ansehen')) ?><span class="sr-only">: <?= e(strip_emphasis((string) $row['label'])) ?> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a><?php endif; ?>
        <a class="download__dl" href="<?= e($m->getUrl()) ?>" download><?= icon('download-simple') ?><span class="sr-only"><?= e(lt('{name} herunterladen ({info})', ['name' => strip_emphasis((string) $row['label']), 'info' => $meta])) ?></span></a>
      </span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Dateien – in der Seitenleiste Dateien oder eine Medienkategorie wählen.</p><?php endif; ?>
</div>
