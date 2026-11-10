<?php

namespace FriendsOfRedaxo\Fluid\Backend;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\Layout;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ExtensionPoint\ContentPageModuleSelect;
use Redaxo\Core\Content\ExtensionPoint\SliceMenu;
use Redaxo\Core\Content\ExtensionPoint\SliceOutput;
use Redaxo\Core\Core;
use Redaxo\Core\View\Asset;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\View\Fragment;

use function array_key_exists;
use function implode;
use function in_array;
use function max;
use function min;
use function preg_replace;
use function str_contains;
use function str_replace;
use function strip_tags;

use function Redaxo\Core\View\escape;

/**
 * Slice-Liste des Backends wie die Website: Die Blöcke eines Layouts stehen im Layout-Panel nebeneinander in ihren
 * Spalten – jeder mit Kopf und Knöpfen des Cores (editieren, löschen, online, verschieben), also weiter echte Slices.
 *
 * Von außen, ohne den Core zu ändern:
 *  - SliceOutput (Bearbeiten-Modus): Panels der Spalten-Blöcke werden eingesammelt statt an ihrer Stelle ausgegeben,
 *    das Layout-Panel enthält je Spalte einen Platzhalter (LayoutBlock::output()).
 *  - Fragment core/structure/content/slice_list.php (fragments/ des AddOns): setzt die Panels in die Platzhalter ein.
 *  - ContentPageModuleSelect: „Block hinzufügen“ in einer Spalte gibt die Spalte mit (fluid_col) – das Formular wählt
 *    sie vor (Form\FieldForm). Je Spalte zusätzlich „+ Block in Spalte n“ am Ende.
 *
 * Wird ein Spalten-Block bearbeitet oder neu angelegt, öffnet assets/slice-grid.js das Formular in einem Dialog über dem
 * Raster; ohne Skript stehen die Spalten untereinander (Formular in voller Breite).
 */
final class SliceGrid
{
    /** Parameter für die Spalte eines neuen Blocks */
    public const string PARAM = 'fluid_col';

    /** @var array<int, array<int, list<string>>> Layout-ID → Spalte → HTML der Spalten-Blöcke */
    private static array $cols = [];

    /** @var array<int, true> Layouts, in deren Spalten gerade ein Block bearbeitet wird */
    private static array $editing = [];

    private static bool $on = false;

    /** Im Backend auf der Inhaltsseite einschalten (FluidAddon::boot) */
    public static function boot(): void
    {
        self::$on = true;
        Fragment::addDirectory(FluidAddon::instance()->getPath('fragments/'));
        Asset::addJsFile(FluidAddon::instance()->getAssetsUrl('slice-grid.js'));   // Formular im Dialog über dem Raster
        Extension::register(ContentPageModuleSelect::class, self::moduleSelect(...));
        Extension::register(SliceMenu::NAME, self::compactMenu(...));   // meldet sich als SLICE_MENU an
    }

    public static function active(): bool
    {
        return self::$on && Core::isBackend();
    }

    /**
     * SliceOutput im Bearbeiten-Modus: Panel eines Spalten-Blocks einsammeln – null = normal ausgeben.
     */
    public static function collect(SliceOutput $ep): ?string
    {
        if (!self::active()) {
            return null;
        }
        $parent = Layout::parentOf($ep->slice);
        if (null === $parent) {
            return null;
        }
        $editing = in_array(Request::get('function', 'string'), ['edit', 'add'], true) && Request::get('slice_id', 'int') === $ep->slice->id;
        if ($editing) {
            self::$editing[$parent->id] = true;
        }
        $n = Layout::count(BlockModule::of($parent)->context()->data);
        self::$cols[$parent->id][min(max(1, Layout::col($ep->slice)), $n)][] = '<div class="fluid-be-item' . ($editing ? ' is-editing' : '') . '"><ul class="rex-slices fluid-be-list">'
            . (string) $ep->subject . '</ul></div>';

        return '';
    }

    /** Platzhalter einer Spalte im Layout-Panel */
    public static function placeholder(int $layoutId, int $col): string
    {
        return '<!--fluid-grid:' . $layoutId . ':' . $col . '-->';
    }

    /**
     * Raster im Layout-Panel: Spalten in ihrer Breite, je Spalte Platzhalter für die Panels und „+ Block in Spalte n“.
     *
     * @param array<string, mixed> $data Werte des Layouts
     */
    public static function grid(ArticleSlice $layout, array $data): string
    {
        $n = Layout::count($data);
        $units = Layout::units($data);
        $next = self::sliceAfterGroup($layout);
        $h = '';
        for ($i = 1; $i <= $n; ++$i) {
            $h .= '<div class="fluid-be-gcol blocks-be-w' . max(1, min(12, (int) ($units[$i - 1] ?? 6))) . '">'
                . '<span class="blocks-be-gcol__label">Spalte ' . $i . '</span>'
                . self::placeholder($layout->id, $i)
                . self::addButton($layout, $next, $i) . '</div>';
        }

        return '<div class="fluid-be-grid fluid-be-cols' . $n . (isset(self::$editing[$layout->id]) ? ' is-editing' : '') . '" data-fluid-grid="' . $layout->id . '">' . $h . '</div>';
    }

    /** Fragment slice_list.php: Panels der Spalten-Blöcke in die Platzhalter setzen */
    public static function assemble(string $content): string
    {
        foreach (self::$cols as $layoutId => $cols) {
            foreach ($cols as $col => $items) {
                $ph = self::placeholder($layoutId, $col);
                $html = implode('', $items);
                // nicht gefunden (Layout offline/anders gefiltert): Blöcke nicht verlieren – ans Ende
                $content = str_contains($content, $ph) ? str_replace($ph, $html, $content) : $content . $html;
            }
            if (isset(self::$editing[$layoutId])) {
                $content = str_replace('data-fluid-grid="' . $layoutId . '"', 'data-fluid-grid="' . $layoutId . '" data-editing', $content);
            }
        }
        self::$cols = [];

        return (string) preg_replace('~<!--fluid-grid:\d+:\d+-->~', '<p class="blocks-be-empty">leer</p>', $content);
    }

    /** „Block hinzufügen“ vor einem Spalten-Block: neue Blöcke landen in derselben Spalte */
    public static function moduleSelect(ContentPageModuleSelect $ep): string
    {
        $select = (string) $ep->subject;
        if ($ep->sliceId <= 0) {
            return $select;
        }
        $slice = ArticleSlice::getArticleSliceById($ep->sliceId, $ep->article->languageId, 0)
            ?? ArticleSlice::getArticleSliceById($ep->sliceId, $ep->article->languageId, 1);   // Arbeitsversion
        if (null === $slice || null === Layout::parentOf($slice)) {
            return $select;
        }

        return str_replace('function=add&amp;', 'function=add&amp;' . self::PARAM . '=' . Layout::col($slice) . '&amp;', $select);
    }

    /**
     * Knöpfe eines Spalten-Blocks als Symbole (Stift, Papierkorb, Status) – Titel und Knöpfe passen in eine Zeile der
     * schmalen Spalte. Die Beschriftung bleibt für Screenreader (hidden_label) und als Tooltip.
     */
    public static function compactMenu(SliceMenu $ep): void
    {
        $slice = ArticleSlice::getArticleSliceById($ep->sliceId, $ep->languageId, 0) ?? ArticleSlice::getArticleSliceById($ep->sliceId, $ep->languageId, 1);
        if (null === $slice || null === Layout::parentOf($slice)) {
            return;
        }
        $icon = static function (array $action, string $name): array {
            if ([] === $action || !isset($action['label'])) {
                return $action;
            }
            $label = (string) $action['label'];
            unset($action['label']);
            $action['hidden_label'] = $label;
            $action['icon'] = $name;
            $action['attributes'] = ['title' => strip_tags($label)] + (array) ($action['attributes'] ?? []);

            return $action;
        };
        $ep->menuEditAction = $icon($ep->menuEditAction, 'edit');
        $ep->menuDeleteAction = $icon($ep->menuDeleteAction, 'delete');
        $ep->menuStatusAction = $icon($ep->menuStatusAction, 1 === $slice->status ? 'online' : 'offline');
    }

    /** Spalte für ein neues Block-Formular (Hinzufügen in einer Spalte) – oder null */
    public static function requestedCol(): ?int
    {
        $col = Request::get(self::PARAM, 'int', 0);

        return self::active() && 'add' === Request::get('function', 'string') && $col > 0 ? $col : null;
    }

    /** „+ Block in Spalte n“: nur Blöcke, die in Spalten stehen dürfen; eingefügt hinter der Gruppe des Layouts */
    private static function addButton(ArticleSlice $layout, int $before, int $col): string
    {
        $items = [];
        $user = Core::getUser();
        foreach (BlockModule::all() as $key => $class) {
            if (!$class::nestable() || ($user && !$user->getComplexPerm('modules')->hasPerm($key))) {
                continue;
            }
            $items[] = ['key' => $key, 'title' => escape($class::label()), 'href' => Url::backendPage('content/edit', [
                'article_id' => $layout->articleId, 'language' => $layout->languageId, 'content_section_id' => $layout->contentSectionId,
                'slice_id' => $before, 'function' => 'add', 'module' => $key, self::PARAM => $col,
            ]) . '#fluid-add'];
        }
        if (!$items) {
            return '';
        }
        $fragment = new Fragment();
        $fragment->setVar('block', true);
        $fragment->setVar('button_label', '+ Block in Spalte ' . $col);
        $fragment->setVar('items', $items, false);

        return '<div class="fluid-be-add">' . $fragment->parse('core/structure/content/module_select.php') . '</div>';
    }

    /** Erster Slice hinter dem Layout und seinen Spalten-Blöcken (-1 = Ende) – neue Spalten-Blöcke kommen davor */
    private static function sliceAfterGroup(ArticleSlice $layout): int
    {
        $children = Layout::children($layout);
        $last = $children ? $children[array_key_last($children)] : $layout;

        return $last->getNextSlice()?->id ?? -1;
    }
}
