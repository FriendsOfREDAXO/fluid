<?php
/**
 * Formular aus YForm (#[AsForm]-Klasse im Projekt): YForm verarbeitet Absenden, Prüfung, CSRF, Honigtopf und Aktionen;
 * fluid gibt es im Stil des Kits aus (YFormBridge::theme). single: unter der Überschrift · split: Kopf links, Formular rechts.
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
use FriendsOfRedaxo\Fluid\Data\YFormBridge;

$v = $b->variant() === 'split' ? 'split' : 'single';
$def = YFormBridge::formDefinition((string) ($d['form'] ?? ''));
$form = '';
if ($def) {
    $form = YFormBridge::runForm($def);
} elseif (is_editing()) {
    $form = '<p class="empty-hint">' . e(YFormBridge::available() ? 'Bitte in der Seitenleiste ein Formular wählen.' : 'Für diesen Block wird das AddOn YForm benötigt.') . '</p>';
}
$note = trim((string) ($d['note'] ?? ''));
?>
<div class="wrap fform fform--<?= e($v) ?>">
  <?= fluid_head($b, $v === 'split' ? 'fform__head' : '') ?>
  <div class="fform__body dff-wrap">
    <?= $form ?>
    <?php if ($note !== '' || (is_editing() && $def)): ?><p class="dff-note fform__note"<?= $b->edit('note', 'lines') ?>><?= nl2br(e($note), false) ?></p><?php endif; ?>
  </div>
</div>
