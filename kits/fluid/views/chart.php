<?php
/** Diagramm: standard (unter dem Kopf) · split (Kopf und Einleitung links, Diagramm rechts). Ausgabe FriendsOfRedaxo\Fluid\Chart\Chart. @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d */
use FriendsOfRedaxo\Fluid\Chart\Chart as Charts;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Chart;

$v = $b->variant() ?: 'standard';
$chart = Charts::render(Chart::spec($d), ['table' => Chart::tableOption($d)]);
if (is_editing() && '' === (string) ($d['source'] ?? '') && trim((string) ($d['labels'] ?? '')) === '') {
    $chart = '<p class="empty-hint">Noch keine Werte – Beschriftungen und Reihen in der Seitenleiste eintragen.</p>';
}
?>
<div class="wrap<?= 'split' === $v ? ' chart-split' : '' ?>">
  <?= fluid_head($b) ?>
  <?= $chart ?>
</div>
