<?php

namespace FriendsOfRedaxo\Fluid\Search;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\PageSettings;
use FriendsOfRedaxo\Fluid\PageTemplates;
use FriendsOfRedaxo\Fluid\Routing\Routes;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\Language;

use function Redaxo\Core\View\escape;

/**
 * Website-Suche (Modell aus KLXM Studio – Core\Search mit Loupe): Stichwortsuche mit Tippfehler-Toleranz und Wortstämmen,
 * Index je Sprache als SQLite im Datenordner des AddOns (data/addons/fluid/search/{sprache}).
 *
 * Nur Öffentliches: veröffentlichte Fassung (live) der Seiten, die online sind (samt Eltern, Sprache online), nicht auf
 * „nicht indexieren“ stehen und keine Seitenvorlage sind. Gelesen werden die Blockdaten selbst (Felder Text, Textarea,
 * Rich-Text; Überschriften gewichtet) – ohne die Seiten zu rendern. Dabei fehlen:
 *   - Offline-Blöcke und Blöcke in den Spalten eines Offline-Layouts (Besucher sehen sie nicht),
 *   - Blöcke mit 'search' => false im Kit,
 *   - Felder mit 'search' => false und Einstellungen, die so nicht auf der Seite stehen (NO_FIELDS: Empfänger und
 *     Bestätigungstexte von Formularen, Platzhalter, Filterwerte, Hinweise für leere Listen …),
 *   - Redaktionskommentare [# … #].
 * Aktualisierung: Veröffentlichen, Struktur- und Inhaltsänderungen (auch im Backend) markieren den Index; die nächste
 * Suche baut ihn neu (Websites dieser Größe in Sekundenbruchteilen), zusätzlich `php bin/console fluid:search-index`.
 * Jeder Treffer wird vor der Ausgabe noch einmal geprüft (visible()) – ein veralteter Index zeigt nie Nicht-Öffentliches.
 */
final class Search
{
    /** Adressen neu berechnet (Struktur geändert) → Suchindex beim nächsten Suchen neu aufbauen */
    public static function boot(): void
    {
        \Redaxo\Core\ExtensionPoint\Extension::register('FLUID_ROUTES_REBUILT', static fn () => self::markDirty());
    }

    private const string DIRTY = 'search_dirty';
    private const array HL = ["\u{E000}", "\u{E001}"];
    /** Einstellungen von Blöcken, die nicht (oder nur in Sonderfällen) sichtbar auf der Seite stehen – auch für Gäste ausgeblendet (fluid_demo) */
    public const array NO_FIELDS = ['placeholder', 'empty_text', 'filter_value', 'mail_to', 'mail_subject', 'confirm_subject', 'confirm_text',
        'form_name', 'success', 'lang', 'redirect'];

    private static function loupe(int $languageId): Loupe
    {
        $dir = Path::addonData('fluid', 'search/' . $languageId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $code = substr(Language::get($languageId)->code ?? 'de', 0, 2);
        $config = Configuration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes(['title', 'headings', 'text'])
            ->withLanguages([$code])
            ->withMaxTotalHits(500)
            ->withMinTokenLengthForPrefixSearch(2);

        return (new LoupeFactory())->create($dir, $config);
    }

    public static function markDirty(): void
    {
        FluidAddon::instance()->setConfig(self::DIRTY, true);
    }

    /** Index aller Sprachen neu aufbauen; gibt die Anzahl der Dokumente zurück */
    public static function rebuild(): int
    {
        $n = 0;
        foreach (Language::getAll() as $lang) {
            $loupe = self::loupe($lang->id);
            $loupe->deleteAllDocuments();
            $docs = self::documents($lang->id);
            foreach (array_chunk($docs, 200) as $chunk) {
                $loupe->addDocuments($chunk);
            }
            $n += \count($docs);
        }
        FluidAddon::instance()->setConfig(self::DIRTY, false);

        return $n;
    }

    /** @return list<array{id: int, title: string, headings: string, text: string}> */
    private static function documents(int $languageId): array
    {
        $noindex = [];
        foreach (Sql::factory()->getArray('SELECT article_id FROM ' . PageSettings::TABLE . ' WHERE noindex = 1 AND language_id = ?', [$languageId]) as $r) {
            $noindex[(int) $r['article_id']] = true;
        }
        $docs = [];
        if (!(Language::get($languageId)?->isOnline() ?? false)) {
            return [];
        }
        foreach (Routes::all() as $id => $byLang) {
            $a = Article::get($id, $languageId);
            if (!isset($byLang[$languageId]) || !$a || !self::visible($a, $noindex)) {
                continue;
            }
            $headings = [];
            $text = [];
            // alle Blöcke der Live-Fassung (auch offline), um Spalten eines Offline-Layouts zu erkennen
            $slices = Sql::factory()->getArray('SELECT module, value1, value2, status, ctype_id FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY ctype_id, priority',
                [$id, $languageId, ArticleRevision::LIVE]);
            $layoutOn = false;   // letzter Abschnitt ist ein Layout, das Besucher sehen
            $ctype = null;
            foreach ($slices as $s) {
                if ($ctype !== $s['ctype_id']) {
                    [$ctype, $layoutOn] = [$s['ctype_id'], false];   // Layouts reichen nicht über ihren Inhaltsbereich hinaus
                }
                $m = Module::get((string) $s['module']);
                $on = 1 === (int) $s['status'];
                if (0 === (int) (BlockModule::decode((string) $s['value2'])['col'] ?? 0)) {
                    $layoutOn = $on && $m instanceof \FriendsOfRedaxo\Fluid\LayoutBlock;
                } elseif (!$layoutOn) {
                    continue;   // Spalten-Block eines Offline-Layouts (oder ohne Layout): nicht sichtbar
                }
                if (!$on || !$m instanceof BlockModule || false === ($m->definition()['search'] ?? true)) {
                    continue;
                }
                self::collect($m->fields(), BlockModule::decode((string) $s['value1']), $headings, $text);
            }
            $s = PageSettings::get($id, $languageId);
            // FLUID_SEARCH_DOCUMENT: Dokument ergänzen (z. B. Texte eigener Daten) oder mit null überspringen
            $doc = \Redaxo\Core\ExtensionPoint\Extension::dispatch(new \Redaxo\Core\ExtensionPoint\ExtensionPoint('FLUID_SEARCH_DOCUMENT',
                ['id' => $id, 'title' => $s['title'] ?: $a->name, 'headings' => implode(' · ', array_unique($headings)), 'text' => trim($s['description'] . ' ' . implode(' ', $text))],
                ['article' => $a]));
            if (\is_array($doc)) {
                $docs[] = $doc;
            }
        }

        return $docs;
    }

    /**
     * Öffentlich sichtbar? Online samt Eltern, nicht „nicht indexieren“, keine Seitenvorlage, nicht die Fehlerseite.
     *
     * @param array<int, true>|null $noindex vorab gelesene Seiten mit „nicht indexieren“ (sonst je Seite gelesen)
     */
    private static function visible(Article $a, ?array $noindex = null): bool
    {
        if (!$a->isOnlineIncludingParents() || PageTemplates::isTemplate($a->id) || !(Language::get($a->languageId)?->isOnline() ?? false)
            || ($a->id === Article::getNotfoundArticleId() && $a->id !== Article::getSiteStartArticleId())) {
            return false;
        }
        if (null !== $noindex) {
            return !isset($noindex[$a->id]);
        }

        return !Sql::factory()->getArray('SELECT 1 FROM ' . PageSettings::TABLE . ' WHERE noindex = 1 AND article_id = ? AND language_id = ? LIMIT 1', [$a->id, $a->languageId]);
    }

    /** Texte der Felder (rekursiv in Wiederholgruppen); Felder „title“/„eyebrow“/„q“ zählen als Überschrift */
    public static function collect(array $fields, array $data, array &$headings, array &$text): void
    {
        foreach ($fields as $f) {
            if (false === ($f['search'] ?? true) || \in_array($f['name'] ?? '', self::NO_FIELDS, true)) {
                continue;
            }
            $v = $data[$f['name']] ?? null;
            if ('repeater' === ($f['type'] ?? '') && \is_array($v)) {
                foreach ($v as $item) {
                    if (\is_array($item)) {
                        self::collect((array) ($f['fields'] ?? []), $item, $headings, $text);
                    }
                }
                continue;
            }
            if (!\in_array($f['type'] ?? 'text', ['text', 'textarea', 'richtext'], true) || !\is_string($v)) {
                continue;
            }
            $plain = trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(str_replace(['<br', '</p>', '</li>', '</h'], [' <br', ' </p>', ' </li>', ' </h'], (string) preg_replace('~\[#.*?#\]~s', '', $v))), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $plain = (string) preg_replace('~\*([^*]+)\*~u', '$1', $plain);
            if ('' === $plain || preg_match('~^(https?://|redaxo://|#|\d+$)~', $plain)) {
                continue;
            }
            \in_array($f['name'], ['title', 'eyebrow', 'q', 'name', 'label'], true) ? $headings[] = $plain : $text[] = $plain;
        }
    }

    /**
     * @return array{total: int, hits: list<array{url: string, title: string, excerpt: string}>}
     */
    public static function search(string $q, int $languageId, int $limit = 20): array
    {
        $q = trim(mb_substr($q, 0, 100));
        if (mb_strlen($q) < 2) {
            return ['total' => 0, 'hits' => []];
        }
        $loupe = self::loupe($languageId);
        if (FluidAddon::instance()->getConfig(self::DIRTY, true) || 0 === $loupe->countDocuments() || $loupe->needsReindex()) {
            self::rebuild();
            $loupe = self::loupe($languageId);
        }
        $params = SearchParameters::create()->withQuery($q)->withHitsPerPage(max(1, min(50, $limit)))
            ->withAttributesToRetrieve(['id', 'title', 'text'])
            ->withAttributesToHighlight(['title', 'text'], self::HL[0], self::HL[1])
            ->withAttributesToCrop(['text'], 160, '…', 1);
        for ($try = 0; ; ++$try) {
            // erst alle Wörter verlangen, ohne Treffer genügt eines
            $res = $loupe->search($params->withMatchingStrategy('all'));
            if (0 === $res->getTotalHits() && str_contains($q, ' ')) {
                $res = $loupe->search($params->withMatchingStrategy('any'));
            }
            $hits = [];
            $stale = false;
            foreach ($res->getHits() as $h) {
                $a = Article::get((int) $h['id'], $languageId);
                // Index veraltet (Seite inzwischen offline, gelöscht, „nicht indexieren“ …): Treffer nie ausgeben
                if (!$a || !self::visible($a)) {
                    $stale = true;
                    continue;
                }
                $f = (array) ($h['_formatted'] ?? []);
                $hits[] = ['url' => $a->getUrl(), 'title' => self::html((string) ($f['title'] ?? $h['title'] ?? $a->name)), 'excerpt' => self::html((string) ($f['text'] ?? ''))];
            }
            if (!$stale) {
                return ['total' => $res->getTotalHits(), 'hits' => $hits];
            }
            if ($try > 0) {
                return ['total' => \count($hits), 'hits' => $hits];
            }
            self::rebuild();
            $loupe = self::loupe($languageId);
        }
    }

    private static function html(string $s): string
    {
        return str_replace(self::HL, ['<mark>', '</mark>'], escape($s));
    }
}
