<?php
/**
 * Datenliste: Einträge einer YForm-Tabelle als Karten, Liste oder Tabelle (Tabelle = #[AsTable]-Klasse aus YForm; Markup und CSS wie der Studio-Block
 * „Datenliste“, css/data.css). Detailansicht auf derselben Seite über ?eintrag={id} (alle Felder als Liste).
 * Angemeldete Redakteure sehen im Bearbeiten-Modus „Bearbeiten“ je Eintrag und „Neuer Eintrag“ (YForm im Backend).
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;

$t = YFormBridge::table((string) ($d['table'] ?? ''));
if (!$t) {
    if (is_editing()) {
        echo '<div class="wrap"><p class="empty-hint">' . e(YFormBridge::available() ? 'Bitte in der Seitenleiste eine YForm-Tabelle wählen.' : 'Für diesen Block wird das AddOn YForm benötigt.') . '</p></div>';
    }

    return;
}
$layout = in_array($b->variant(), ['cards', 'list', 'table'], true) ? $b->variant() : 'cards';
$titleF = YFormBridge::field($t, (string) ($d['title_field'] ?? ''), [\Yakamara\YForm\Field\Text::class]);
$imageF = YFormBridge::field($t, (string) ($d['image_field'] ?? ''), [\Yakamara\YForm\Field\Media::class]);
$textF = YFormBridge::field($t, (string) ($d['text_field'] ?? ''), [\Yakamara\YForm\Field\Textarea::class]);
$metaF = YFormBridge::field($t, (string) ($d['meta_field'] ?? ''));
$filterF = YFormBridge::field($t, (string) ($d['filter_field'] ?? ''));
$same = static fn ($a, $c) => $a && $c && $a->name === $c->name;
$filterV = trim((string) ($d['filter_value'] ?? ''));
$here = Url::article($b->slice->articleId, Language::getCurrentId());
$detailOn = !empty($d['detail']);
$href = static fn (int $id) => $here . (str_contains($here, '?') ? '&' : '?') . 'eintrag=' . $id . '#' . $b->domId();
$edit = is_editing() ? static fn (?int $id = null) => YFormBridge::editUrl($t, $id) : static fn (?int $id = null) => null;
$editBtn = static fn (?string $url, string $label) => $url ? '<a class="fdl-edit" href="' . e($url) . '" target="_blank" rel="noopener">' . e($label) . '</a>' : '';
$title = static fn ($row) => $titleF ? YFormBridge::text($row, $titleF) : '#' . $row->id;
$pic = static fn ($row, string $sizes, string $ratio) => '' !== ($file = YFormBridge::file($row, $imageF)) ? img($file, $sizes, ['ratio' => $ratio]) : '';

// ------------------------------------------------------------------ Detailansicht
$entry = $detailOn ? Request::get('eintrag', 'int', 0) : 0;
$row = $entry > 0 ? YFormBridge::row($t, $entry, $filterF, $filterV) : null;
if ($row):
    $img = $pic($row, '(min-width: 1280px) 1200px, 100vw', '16:9');
?>
<div class="wrap df df--head fdl-detail">
  <p class="df-back"><a href="<?= e($here . '#' . $b->domId()) ?>">← <?= e(lt('Zur Übersicht')) ?></a></p>
  <header class="df-head">
    <?php if ($metaF && ($m = YFormBridge::html($row, $metaF)) !== ''): ?><p class="df-meta"><?= $m ?></p><?php endif; ?>
    <h2 class="h2 df-title"><?= e($title($row)) ?></h2>
    <?= $editBtn($edit($row->id), 'Eintrag bearbeiten') ?>
    <?php if ($img !== ''): ?><div class="df-img ratio-16-9"><?= $img ?></div><?php endif; ?>
  </header>
  <dl class="df-dl">
    <?php foreach (YFormBridge::detailFields($t) as $f): if ($same($f, $titleF) || $same($f, $imageF) || $same($f, $filterF) || ($h = YFormBridge::html($row, $f)) === '') continue; ?>
    <div><dt><?= e($f->translatedLabel()) ?></dt><dd><?= $h ?></dd></div>
    <?php endforeach; ?>
  </dl>
</div>
<?php
    return;
endif;

// ------------------------------------------------------------------ Liste
$rows = YFormBridge::rows($t, [
    'filter' => $filterF, 'value' => $filterV,
    'sort' => YFormBridge::field($t, (string) ($d['sort_field'] ?? '')), 'dir' => (string) ($d['sort_dir'] ?? 'asc'),
    'limit' => (int) ($d['limit'] ?? 0),
]);
$cols = ['s' => 4, 'm' => 3, 'l' => 2][$d['size'] ?? 'm'] ?? 3;
$hTag = trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
?>
<div class="wrap dl dl--<?= e($layout) ?>">
  <?= fluid_head($b, 'dl-head') ?>
  <?php if (!$rows): ?>
  <p class="dl-empty"><?= e(trim((string) ($d['empty_text'] ?? '')) ?: lt('Zurzeit gibt es hier keine Einträge.')) ?></p>
  <?php elseif ($layout === 'table'): ?>
  <div class="dl-tablewrap"><table class="dl-table">
    <thead><tr>
      <th scope="col"><?= e($titleF ? $titleF->translatedLabel() : lt('Eintrag')) ?></th>
      <?php foreach ([$metaF, $textF] as $f): if (!$f || $same($f, $titleF)) continue; ?><th scope="col"><?= e($f->translatedLabel()) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $detailOn ? '<a href="' . e($href($r->id)) . '">' . e($title($r)) . '</a>' : e($title($r)) ?> <?= $editBtn($edit($r->id), 'Bearbeiten') ?></td>
        <?php foreach ([$metaF, $textF] as $f): if (!$f || $same($f, $titleF)) continue; ?><td><?= YFormBridge::html($r, $f) ?></td><?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <ul class="dl-items<?= $layout === 'cards' ? ' dl-cols-' . $cols : '' ?>" role="list">
    <?php foreach ($rows as $r): $name = $title($r); $url = $detailOn ? $href($r->id) : null; ?>
    <li class="dl-item"><?= $editBtn($edit($r->id), 'Bearbeiten') ?>
      <?php if ($imageF): $p = $pic($r, $layout === 'cards' ? '(min-width: 1080px) 400px, 100vw' : '(min-width: 1080px) 320px, 40vw', '16:10'); ?>
      <div class="dl-img ratio-16-10"><?= $p !== '' ? $p : '<span class="dl-ph" aria-hidden="true"></span>' ?></div>
      <?php endif; ?>
      <div class="dl-body">
        <?php if ($metaF && !$same($metaF, $titleF) && ($m = YFormBridge::html($r, $metaF)) !== ''): ?><p class="dl-meta"><?= $m ?></p><?php endif; ?>
        <<?= $hTag ?> class="dl-name"><?= $url ? '<a href="' . e($url) . '" class="dl-link">' . e($name) . '</a>' : e($name) ?></<?= $hTag ?>>
        <?php if ($textF && !$same($textF, $titleF) && ($x = YFormBridge::text($r, $textF)) !== ''): ?><div class="dl-f dl-clamp dl-clamp-3"><p><?= e(mb_strimwidth(strip_tags($x), 0, 320, ' …')) ?></p></div><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($new = $edit()): ?><p class="fdl-new"><?= $editBtn($new, '+ Neuer Eintrag') ?></p><?php endif; ?>
</div>
