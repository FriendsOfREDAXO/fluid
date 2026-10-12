<?php /** Karte (Kopf im Kit-Stil, Ausgabe FriendsOfRedaxo\Fluid\Maps\Maps). @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?= \FriendsOfRedaxo\Fluid\Maps\Maps::renderBlock($d) ?>
</div>
