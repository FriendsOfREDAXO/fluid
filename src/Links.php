<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;

use function Redaxo\Core\View\escape;

/**
 * Artikel der Struktur für die Linkauswahl der Formatierungsleiste (`<script type="application/json" id="blocks-links">`).
 * Gespeichert wird `href` (aktuelle Adresse) + `data-link="redaxo://ID"`; die Ausgabe aktualisiert die Adresse.
 */
final class Links
{
    /** @return list<array{id: int, name: string, path: string, url: string, online: bool}> */
    public static function all(?int $languageId = null): array
    {
        $languageId ??= Language::getCurrentId();
        $out = [];
        $add = static function (Article $a, string $path) use (&$out): void {
            $out[] = ['id' => $a->id, 'name' => $a->name, 'path' => $path, 'url' => $a->getUrl(), 'online' => $a->isOnline()];
        };
        $walk = static function (array $cats, string $path) use (&$walk, $add): void {
            foreach ($cats as $c) {
                /** @var Category $c */
                $p = '' === $path ? $c->name : $path . ' › ' . $c->name;
                foreach ($c->getArticles() as $a) {
                    $add($a, $a->isStartArticle() ? $p : $p . ' › ' . $a->name);
                }
                $walk($c->getChildren(), $p);
            }
        };
        foreach (Article::getRootArticles(false, $languageId) as $a) {
            $add($a, 'Hauptebene');
        }
        $walk(Category::getRootCategories(false, $languageId), '');

        return $out;
    }

    public static function json(?int $languageId = null): string
    {
        $linkmap = Url::backendPage('linkmap', ['opener_input_field' => 'blocks_pick', 'language' => $languageId ?? Language::getCurrentId()]);

        return '<script type="application/json" id="blocks-links" data-linkmap="' . escape($linkmap) . '">'
            . json_encode(self::all($languageId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
