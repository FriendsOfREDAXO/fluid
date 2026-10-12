<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Database\Sql;

/**
 * Entwürfe-Übersicht (Modell aus KLXM Studio – Core\Review\Drafts): Seiten, deren Arbeitsversion vom Live-Stand abweicht
 * (Blöcke, Reihenfolge, Inhalte oder Status). Vergleich per Prüfsumme je Fassung – eine Abfrage für alle Seiten.
 */
final class Drafts
{
    /** @return list<array{id: int, name: string, url: string, changed: string, user: string}> */
    public static function all(int $languageId, ?\Redaxo\Core\Security\User $user = null): array
    {
        if (ArticleRevision::LIVE === FluidAddon::revision()) {
            return [];
        }
        $sql = Sql::factory();
        $sql->setQuery('SET SESSION group_concat_max_len = 16777216');   // Prüfsumme über den ganzen Inhalt
        $rows = $sql->getArray(
            'SELECT article_id, revision, MD5(GROUP_CONCAT(CONCAT_WS("|", module, priority, status, value1, value2, ctype_id) ORDER BY priority SEPARATOR "#")) AS sig,
                MAX(updatedate) AS changed, SUBSTRING_INDEX(GROUP_CONCAT(updateuser ORDER BY updatedate DESC), ",", 1) AS user
             FROM rex_article_slice WHERE language_id = ? GROUP BY article_id, revision',
            [$languageId],
        );
        $by = [];
        foreach ($rows as $r) {
            $by[(int) $r['article_id']][(int) $r['revision']] = $r;
        }
        $out = [];
        foreach ($by as $id => $rev) {
            $work = $rev[ArticleRevision::WORK] ?? null;
            $live = $rev[ArticleRevision::LIVE] ?? null;
            if (!$work || ($live && $live['sig'] === $work['sig']) || PageTemplates::isTemplate($id)) {
                continue;
            }
            $a = Article::get($id, $languageId);
            // nur Seiten, auf die der Benutzer Strukturrechte hat
            $perm = $user?->getComplexPerm('structure');
            if ($a && $user && !$user->admin && !($perm instanceof \Redaxo\Core\Content\StructurePermission && $perm->hasCategoryPerm($a->categoryId))) {
                continue;
            }
            if ($a) {
                $out[] = ['id' => $id, 'name' => $a->name, 'url' => $a->getUrl(), 'changed' => (string) $work['changed'], 'user' => (string) $work['user']];
            }
        }
        usort($out, static fn ($a, $b) => strcmp($b['changed'], $a['changed']));

        return $out;
    }
}
