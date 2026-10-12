<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\Block;
use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\PageSettings;
use FriendsOfRedaxo\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Language\Language;

use function is_array;
use function is_callable;

/**
 * Strukturierte Daten (schema.org, JSON-LD) als verknüpfter @graph je Seite (Modell aus KLXM Studio – Core\StructuredData):
 *  - Organisation: vollständig auf der Startseite, sonst als Verweis (Website-Einstellungen, Seo::organizationNode).
 *  - WebSite, WebPage, BreadcrumbList: automatisch.
 *  - Blöcke erklären ihre Daten im Kit: 'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a']
 *    oder eine Funktion fn(Block $b): ?array. Gesammelt beim Rendern (nicht im Bearbeiten-Modus).
 */
final class StructuredData
{
    /** @var list<array<string, mixed>> */
    private static array $nodes = [];
    private static int $faq = 0;

    public static function collect(Block $b): void
    {
        if (Editing::active()) {
            return;
        }
        $spec = $b->module->definition()['jsonld'] ?? null;
        if (is_callable($spec)) {
            if ($n = $spec($b)) {
                self::$nodes[] = $n;
            }

            return;
        }
        if (is_array($spec) && 'faq' === ($spec['type'] ?? '')) {
            $qs = [];
            foreach ((array) ($b->data[$spec['items'] ?? 'items'] ?? []) as $it) {
                $q = trim(strip_tags((string) preg_replace('~\*([^*]+)\*~u', '$1', (string) ($it[$spec['question'] ?? 'q'] ?? ''))));
                $a = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags((string) ($it[$spec['answer'] ?? 'a'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
                if ('' !== $q && '' !== $a && !str_contains($q . $a, '[')) {
                    $qs[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
                }
            }
            if ($qs) {
                self::$nodes[] = ['@type' => 'FAQPage', '@id' => '#faq' . (++self::$faq), 'mainEntity' => $qs];
            }
        }
    }

    /** @return array<string, mixed> */
    public static function graph(Article $article): array
    {
        $url = Router::canonical($article->id, $article->languageId);
        $home = Router::canonical(Article::getSiteStartArticleId(), $article->languageId);
        $lang = Language::get($article->languageId)?->code ?? 'de';
        $org = Seo::organizationNode();
        $isHome = $article->id === Article::getSiteStartArticleId();
        $graph = [];
        $graph[] = $isHome ? $org : ['@type' => $org['@type'], '@id' => $org['@id'], 'name' => $org['name'], 'url' => $org['url']];
        $graph[] = ['@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => Settings::name(), 'inLanguage' => $lang, 'publisher' => ['@id' => $org['@id']]];
        // Brotkrumen: Startseite › Elternseiten › Seite
        $crumbs = [];
        if (!$isHome) {
            $crumbs[] = [Article::getSiteStartArticle()?->name ?? 'Start', $home];
            foreach ($article->path as $cid) {
                if (($c = Category::get($cid, $article->languageId)) && $c->isOnline()) {
                    $crumbs[] = [$c->name, Router::canonical($c->id, $article->languageId)];
                }
            }
            if (!$article->isStartArticle() || !in_array($article->id, $article->path, true)) {
                $crumbs[] = [$article->name, $url];
            }
        }
        $s = PageSettings::get($article->id, $article->languageId);
        $page = array_filter([
            '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => PageSettings::title($article, Settings::name()),
            'description' => $s['description'] ?: (string) Settings::get('default_meta_description', '') ?: null, 'inLanguage' => $lang,
            'isPartOf' => ['@id' => $home . '#website'], 'breadcrumb' => \count($crumbs) > 1 ? ['@id' => $url . '#breadcrumb'] : null,
            'dateModified' => $article->updateDate ? date('c', $article->updateDate) : null,
        ]);
        if (\count($crumbs) > 1) {
            $items = [];
            foreach ($crumbs as $i => [$name, $href]) {
                $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $href];
            }
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items];
        }
        $graph[] = $page;
        foreach (self::$nodes as $n) {
            if (isset($n['@id']) && str_starts_with((string) $n['@id'], '#')) {
                $n['@id'] = $url . $n['@id'];
            }
            $graph[] = $n;
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
