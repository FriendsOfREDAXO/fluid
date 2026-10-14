<?php
/**
 * Formular aus YForm (#[AsForm]-Klasse im Projekt): YForm verarbeitet Absenden, Prüfung, CSRF, Honigtopf und Aktionen;
 * fluid gibt es im Stil des Kits aus (YFormBridge::theme). single: unter der Überschrift · split: Kopf links, Formular rechts.
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
use FriendsOfRedaxo\Fluid\Data\YFormBridge;

$v = $b->variant() === 'split' ? 'split' : 'single';
// Formular-Klasse aus dem Projekt (#[AsForm]) oder das eigene Formular aus den Feldern des Blocks (Data\BuiltForm)
$def = YFormBridge::formDefinition((string) ($d['form'] ?? ''));
if (!$def && YFormBridge::available() && [] !== (array) ($d['fields'] ?? [])) {
    $def = \FriendsOfRedaxo\Fluid\Data\BuiltForm::make($b->id, $d, \Redaxo\Core\Content\Article::getCurrent());
}
$form = '';
if ($def) {
    $form = YFormBridge::runForm($def);
    if (is_editing() && $def instanceof \FriendsOfRedaxo\Fluid\Data\BuiltForm) {
        $mode = ['save_mail' => 'speichert und sendet per E-Mail', 'save' => 'speichert', 'mail' => 'sendet per E-Mail'][$d['mode'] ?? 'save_mail'] ?? '';
        $warn = in_array($d['mode'] ?? 'save_mail', ['mail', 'save_mail'], true) && '' === trim((string) ($d['mail_to'] ?? '')) ? ' – <b>E-Mail-Empfänger fehlt</b>' : '';
        $form = '<p class="empty-hint">Eigenes Formular: ' . e($mode) . $warn . '. Felder und Ablauf in der Seitenleiste.</p>' . $form;
    }
} elseif (is_editing()) {
    $form = '<p class="empty-hint">' . e(YFormBridge::available() ? 'Bitte in der Seitenleiste Felder anlegen oder ein Formular wählen.' : 'Für diesen Block wird das AddOn YForm benötigt.') . '</p>';
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
