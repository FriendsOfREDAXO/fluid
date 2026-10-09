<?php
/**
 * Formular einer YForm-Tabelle (FriendsOfRedaxo\Fluid\Data\YFormBridge): Felder, Pflichtangaben und Prüfungen aus dem
 * Table Manager; Eintrag wird gespeichert, optional E-Mail an die Redaktion. single: unter der Überschrift · split: Kopf
 * links, Formular rechts (Sidebar-Muster). @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;

$v = $b->variant() === 'split' ? 'split' : 'single';
$table = YFormBridge::table((string) ($d['table'] ?? ''));
$form = '';
if ($table) {
    $form = YFormBridge::form($table, [
        'id' => $b->domId(),
        'action' => Url::article($b->slice->articleId, Language::getCurrentId()),
        'submit' => (string) ($d['submit_label'] ?? ''),
        'success' => (string) ($d['success'] ?? ''),
        'notify' => (string) ($d['notify'] ?? ''),
        'subject' => trim(strip_emphasis((string) ($d['title'] ?? ''))),
    ]);
} elseif (is_editing()) {
    $form = '<p class="empty-hint">' . e(YFormBridge::available() ? 'Bitte in der Seitenleiste eine YForm-Tabelle wählen.' : 'Für diesen Block wird das AddOn YForm benötigt.') . '</p>';
}
$note = trim((string) ($d['note'] ?? ''));
?>
<div class="wrap fform fform--<?= e($v) ?>">
  <?= fluid_head($b, $v === 'split' ? 'fform__head' : '') ?>
  <div class="fform__body dff-wrap">
    <?= $form ?>
    <?php if ($note !== '' || (is_editing() && $table)): ?><p class="dff-note fform__note"<?= $b->edit('note', 'lines') ?>><?= nl2br(e($note), false) ?></p><?php endif; ?>
  </div>
</div>
