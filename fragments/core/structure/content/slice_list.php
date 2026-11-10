<?php

use FriendsOfRedaxo\Fluid\Backend\SliceGrid;
use Redaxo\Core\View\Fragment;

/**
 * Slice-Liste des Backends (überschreibt das Fragment des Cores, gleiche Variablen): setzt die Panels der Spalten-Blöcke
 * in das Raster ihres Layouts (FriendsOfRedaxo\Fluid\Backend\SliceGrid). Ohne Layouts unverändert.
 *
 * - string $content  The rendered HTML of all slice <li> elements
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
if ('' === $this->content) {
    return;
}
?>
<ul class="rex-slices">
    <?= SliceGrid::assemble($this->content) ?>
</ul>
