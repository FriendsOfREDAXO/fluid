<?php

namespace FriendsOfRedaxo\Fluid\Mcp;

use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\Blog\Blog;
use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\Search\Search;
use FriendsOfRedaxo\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Language\Language;
use Yakamara\YForm\Validation\ValidationException;

use function is_array;

/**
 * Werkzeuge des MCP-Servers. Jedes Werkzeug sagt, ob es schreibt (write) und ob es öffentlich sein darf (public).
 * Die Liste richtet sich nach dem Zugang: öffentlich nur Freigegebenes, Schlüssel „read“ ohne Schreib-Werkzeuge.
 */
final class Tools
{
    private readonly Content $content;

    public function __construct(private readonly Access $access)
    {
        $this->content = new Content($access);
    }

    /** @return array<string, array<string, mixed>> Name => Definition (mit run) */
    public function all(): array
    {
        $cfg = Access::config();
        $str = static fn (string $d) => ['type' => 'string', 'description' => $d];
        $int = static fn (string $d) => ['type' => 'integer', 'description' => $d];
        $bool = static fn (string $d) => ['type' => 'boolean', 'description' => $d];
        $obj = static fn (string $d) => ['type' => 'object', 'description' => $d, 'additionalProperties' => true];
        $lang = $str('Sprache (Kürzel, z. B. de, en) – Standard: Startsprache');
        $page = ['description' => 'Seite: ID, Pfad (z. B. /kontakt) oder „home“', 'type' => ['integer', 'string']];
        $schema = static fn (array $props, array $required = []) => ['type' => 'object', 'properties' => (object) $props] + ($required ? ['required' => $required] : []);
        $ro = ['readOnlyHint' => true, 'openWorldHint' => false];
        $rw = ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false];
        $del = ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false];
        $public = $this->access->isPublic();
        $c = $this->content;

        $t = [];
        $add = function (string $name, string $title, string $desc, array $input, array $ann, bool $write, bool $allowPublic, callable $run) use (&$t, $public): void {
            if (($write && !$this->access->write) || ($public && !$allowPublic)) {
                return;
            }
            $t[$name] = ['name' => $name, 'title' => $title, 'description' => $desc, 'inputSchema' => $input, 'annotations' => $ann + ['title' => $title], 'run' => $run];
        };

        $add('site_overview', 'Website-Überblick', 'Name, Adresse, Sprachen, Blogs, Datentabellen und was dieser Zugang darf. Damit beginnen.',
            $schema([]), $ro, false, true, fn () => $this->overview());

        $add('list_pages', 'Seitenbaum', 'Alle Seiten als Baum (Menüpunkte mit Unterseiten)' . ($public ? ', nur veröffentlichte.' : ' – auch offline Seiten.'),
            $schema(['lang' => $lang]), $ro, false, (bool) $cfg['public_pages'], fn (array $a) => ['pages' => $c->tree($c->language($a['lang'] ?? null))]);

        $add('get_page', 'Seite lesen', 'Seite mit allen Blöcken (Typ, Felder als JSON). ' . ($public ? 'Veröffentlichte Fassung.' : 'Standard: Entwurf – version „live“ zeigt die veröffentlichte Fassung.'),
            $schema(['page' => $page, 'lang' => $lang] + ($public ? [] : ['version' => ['type' => 'string', 'enum' => ['draft', 'live'], 'description' => 'draft (Standard) oder live']]), ['page']),
            $ro, false, (bool) $cfg['public_pages'], function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->pageInfo($art) + ['blocks' => $c->blocks($art, 'live' === ($a['version'] ?? 'draft'))];
            });

        $add('search', 'Suche', 'Volltextsuche über die veröffentlichten Seiten (wie die Suche der Website).',
            $schema(['query' => $str('Suchbegriff'), 'lang' => $lang, 'limit' => $int('Höchstens so viele Treffer (Standard 10, max. 50)')], ['query']),
            $ro, false, (bool) $cfg['public_search'], function (array $a) use ($c) {
                $res = Search::search((string) ($a['query'] ?? ''), $c->language($a['lang'] ?? null), (int) ($a['limit'] ?? 10));
                foreach ($res['hits'] as &$h) {
                    $h['url'] = Content::abs($h['url']);
                    // Hervorhebung der Website (<mark>) als **…** – Werkzeuge lesen Text
                    $h['title'] = html_entity_decode(strip_tags(str_replace(['<mark>', '</mark>'], '**', $h['title'])));
                    $h['excerpt'] = html_entity_decode(strip_tags(str_replace(['<mark>', '</mark>'], '**', $h['excerpt'])));
                }
                unset($h);

                return $res;
            });

        $add('list_posts', 'Blog-Beiträge', 'Beiträge eines Blogs (neueste zuerst) mit Datum, Autor, Schlagworten. Blogs stehen in site_overview.',
            $schema(['blog' => $int('ID des Blogs'), 'lang' => $lang, 'limit' => $int('Anzahl (Standard 20)')], ['blog']),
            $ro, false, (bool) $cfg['public_blog'], fn (array $a) => $this->posts((int) ($a['blog'] ?? 0), $c->language($a['lang'] ?? null), (int) ($a['limit'] ?? 20)));

        $add('list_block_types', 'Blocktypen', 'Blocktypen mit ihren Feldern (Name, Typ, Pflicht, Auswahlwerte). Vor add_block/update_block lesen.',
            $schema(['type' => $str('Nur diesen Blocktyp')]), $ro, false, false, fn (array $a) => ['block_types' => $c->blockTypes(isset($a['type']) ? (string) $a['type'] : null)]);

        if (YFormBridge::available() && [] !== $this->access->tables()) {
            $tableProp = ['type' => 'string', 'enum' => $this->access->tables(), 'description' => 'Tabelle'];
            $add('list_entries', 'Einträge einer Datentabelle', 'Einträge einer freigegebenen YForm-Tabelle, seitenweise. Felder als lesbarer Text.',
                $schema(['table' => $tableProp, 'page' => $int('Seite (ab 1)'), 'per_page' => $int('Einträge je Seite (Standard 20, max. 100)')], ['table']),
                $ro, false, true, fn (array $a) => $this->entries((string) ($a['table'] ?? ''), (int) ($a['page'] ?? 1), (int) ($a['per_page'] ?? 20)));
            $add('get_entry', 'Eintrag lesen', 'Einen Eintrag einer freigegebenen YForm-Tabelle.',
                $schema(['table' => $tableProp, 'id' => $int('ID des Eintrags')], ['table', 'id']),
                $ro, false, true, fn (array $a) => $this->entry((string) ($a['table'] ?? ''), (int) ($a['id'] ?? 0)));
            $add('save_entry', 'Eintrag anlegen oder ändern', 'Legt einen Eintrag an (ohne id) oder ändert ihn (mit id). values = Feldname → Wert; YForm prüft die Eingaben.',
                $schema(['table' => $tableProp, 'id' => $int('ID zum Ändern, leer = neu'), 'values' => $obj('Feldname → Wert')], ['table', 'values']),
                $rw, true, false, fn (array $a) => $this->saveEntry((string) ($a['table'] ?? ''), (int) ($a['id'] ?? 0), is_array($a['values'] ?? null) ? $a['values'] : []));
        }

        $add('update_block', 'Block ändern', 'Felder eines Blocks ändern (im Entwurf). data = nur die zu ändernden Felder; replace=true ersetzt alle Felder.',
            $schema(['page' => $page, 'block' => $int('ID des Blocks (aus get_page)'), 'data' => $obj('Feldname → Wert'), 'replace' => $bool('Alle Felder ersetzen statt zusammenführen'), 'lang' => $lang], ['page', 'block', 'data']),
            $rw, true, false, function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->updateBlock($art, (int) ($a['block'] ?? 0), is_array($a['data'] ?? null) ? $a['data'] : [], (bool) ($a['replace'] ?? false));
            });

        $add('add_block', 'Block einfügen', 'Neuen Block einfügen (im Entwurf). after = Block-ID, hinter dem er steht (0 = ganz oben, weglassen = ans Ende).',
            $schema(['page' => $page, 'type' => $str('Blocktyp (Schlüssel aus list_block_types)'), 'data' => $obj('Feldname → Wert'), 'after' => $int('Block-ID; 0 = oben'), 'lang' => $lang], ['page', 'type']),
            $rw, true, false, function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->addBlock($art, (string) ($a['type'] ?? ''), is_array($a['data'] ?? null) ? $a['data'] : [], isset($a['after']) ? (int) $a['after'] : -1);
            });

        $add('move_block', 'Block verschieben', 'Abschnitt vor einen anderen Block setzen (before = Block-ID, 0 = ans Ende). Blöcke in Layout-Spalten nicht.',
            $schema(['page' => $page, 'block' => $int('ID des Blocks'), 'before' => $int('vor diesen Block; 0 = ans Ende'), 'lang' => $lang], ['page', 'block', 'before']),
            $rw, true, false, function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->moveBlock($art, (int) ($a['block'] ?? 0), (int) ($a['before'] ?? 0));
            });

        $add('set_block_online', 'Block ein-/ausschalten', 'Block online oder offline schalten (im Entwurf).',
            $schema(['page' => $page, 'block' => $int('ID des Blocks'), 'online' => $bool('true = online'), 'lang' => $lang], ['page', 'block', 'online']),
            $rw, true, false, function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->setOnline($art, (int) ($a['block'] ?? 0), (bool) ($a['online'] ?? true));
            });

        $add('delete_block', 'Block löschen', 'Block löschen (im Entwurf; ein Layout samt seinen Spalten). Der Stand davor bleibt als Version.',
            $schema(['page' => $page, 'block' => $int('ID des Blocks'), 'confirm' => $bool('Muss true sein'), 'lang' => $lang], ['page', 'block', 'confirm']),
            $del, true, false, function (array $a) use ($c) {
                if (true !== ($a['confirm'] ?? false)) {
                    throw new ApiError('Zum Löschen confirm: true setzen.');
                }
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->deleteBlock($art, (int) ($a['block'] ?? 0));
            });

        $add('create_page', 'Seite anlegen', 'Neue Seite (offline). parent = ID eines Menüpunkts (0 = oberste Ebene); menu=true legt einen Menüpunkt an. In einem Blog = neuer Beitrag.',
            $schema(['name' => $str('Titel'), 'parent' => $int('Menüpunkt (Kategorie-ID), 0 = oberste Ebene'), 'menu' => $bool('Als Menüpunkt'), 'template' => $int('ID einer Seitenvorlage (optional)'), 'lang' => $lang], ['name']),
            $rw, true, false, fn (array $a) => $c->createPage((string) ($a['name'] ?? ''), (int) ($a['parent'] ?? 0), (bool) ($a['menu'] ?? false), $c->language($a['lang'] ?? null), (int) ($a['template'] ?? 0)));

        if ($this->access->publish) {
            $add('publish_page', 'Seite veröffentlichen', 'Entwurf einer Seite veröffentlichen (live). Vorher mit dem Menschen abstimmen.',
                $schema(['page' => $page, 'lang' => $lang], ['page']), $rw, true, false, function (array $a) use ($c) {
                    return $c->publish($c->article($a['page'] ?? null, $c->language($a['lang'] ?? null)));
                });
        }

        return $t;
    }

    /** @return array<string, mixed> */
    private function overview(): array
    {
        $cfg = Access::config();
        $langs = [];
        foreach (Language::getAll() as $l) {
            $langs[] = ['code' => $l->code, 'name' => $l->name, 'start' => $l->id === Language::getStartId()];
        }
        $blogs = [];
        if (!$this->access->isPublic() || $cfg['public_blog']) {
            foreach (Blog::all() as $id => $name) {
                $blogs[] = ['id' => $id, 'name' => $name];
            }
        }

        return array_filter([
            'name' => (string) (Settings::get('org_name') ?: Core::getProject()->instanceName),
            'url' => \Redaxo\Core\Filesystem\Url::absoluteBase(),
            'home' => Article::getSiteStartArticleId(),
            'languages' => $langs,
            'blogs' => $blogs,
            'tables' => $this->access->tables(),
            'access' => $this->access->describe(),
            'notes' => $this->access->isPublic() ? null : 'Änderungen landen im Entwurf (Versionen bleiben erhalten). Rich-Text als HTML ohne Bilder; Bilder über Medienfelder.',
        ], static fn ($v) => null !== $v && [] !== $v);
    }

    /** @return array<string, mixed> */
    private function posts(int $blog, int $lang, int $limit): array
    {
        if (!Blog::isBlog($blog)) {
            throw new ApiError('Kein Blog mit dieser ID (siehe site_overview).');
        }
        $start = Article::get($blog, $lang);
        if (null === $start || !$this->access->canReadPage($start)) {
            throw new ApiError('Blog nicht gefunden.');
        }
        $res = Blog::posts($blog, ['limit' => max(1, min(100, $limit)), 'language' => $lang, 'drafts' => !$this->access->isPublic()]);
        $out = [];
        foreach ($res['posts'] as $p) {
            if (!$this->access->canReadPage($p->article)) {
                continue;
            }
            $out[] = ['id' => $p->article->id, 'title' => $p->article->name, 'url' => Content::abs($p->url), 'online' => $p->article->isOnline(),
                'date' => $p->date->format('Y-m-d'), 'author' => $p->author, 'tags' => $p->tags, 'excerpt' => $p->excerpt];
        }

        return ['blog' => $blog, 'total' => $res['total'], 'posts' => $out];
    }

    private function table(string $name, string $need): \Yakamara\YForm\Table\Table
    {
        $mode = $this->access->table($name);
        if (null === $mode || ('write' === $need && 'write' !== $mode)) {
            throw new ApiError('Tabelle „' . $name . '“ ist für diesen Zugang nicht ' . ('write' === $need ? 'zum Ändern ' : '') . 'freigegeben.');
        }

        return YFormBridge::table($name) ?? throw new ApiError('Tabelle „' . $name . '“ gibt es nicht.');
    }

    /** @return array<string, mixed> */
    private function record(\Yakamara\YForm\Table\Table $table, \Yakamara\YForm\Data\Record $r): array
    {
        $out = ['id' => (int) $r->get('id'), 'label' => $table->recordLabel($r)];
        foreach (YFormBridge::detailFields($table, true) as $f) {
            if ($f instanceof \FriendsOfRedaxo\Fluid\Data\FluidBlocks) {
                $out[$f->name] = \FriendsOfRedaxo\Fluid\BlockData::text($r->get($f->name));
                continue;
            }
            $out[$f->name] = YFormBridge::text($r, $f);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function entries(string $name, int $page, int $perPage): array
    {
        $table = $this->table($name, 'read');
        $res = YFormBridge::page($table, [], max(1, $page), max(1, min(100, $perPage)));

        return ['table' => $name, 'label' => $table->label(), 'fields' => array_map(static fn ($f) => ['name' => $f->name, 'label' => $f->translatedLabel(), 'type' => (new \ReflectionClass($f))->getShortName()], YFormBridge::detailFields($table, true)),
            'total' => $res['total'], 'page' => $res['page'], 'pages' => $res['pages'], 'entries' => array_map(fn ($r) => $this->record($table, $r), $res['rows'])];
    }

    /** @return array<string, mixed> */
    private function entry(string $name, int $id): array
    {
        $table = $this->table($name, 'read');
        $r = $table::find($id) ?? throw new ApiError('Eintrag ' . $id . ' gibt es nicht.');

        return $this->record($table, $r);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function saveEntry(string $name, int $id, array $values): array
    {
        $table = $this->table($name, 'write');
        $r = $id > 0 ? ($table::find($id) ?? throw new ApiError('Eintrag ' . $id . ' gibt es nicht.')) : $table->newRecord();
        $known = [];
        foreach (YFormBridge::detailFields($table, false) as $f) {
            if (array_key_exists($f->name, $values)) {
                $r->set($f->name, $f->fromInput($values[$f->name]));
                $known[] = $f->name;
            }
        }
        if ([] === $known) {
            throw new ApiError('Keine bekannten Felder in „values“ (Felder mit list_entries).');
        }
        try {
            $r->save();
        } catch (ValidationException $e) {
            throw new ApiError('Eingaben nicht gültig: ' . implode(' ', $e->errors->all()));
        }
        \FriendsOfRedaxo\Fluid\Activity::log('save', null, $table->label() . ' #' . $r->get('id') . ' (MCP)');

        return ['table' => $name, 'id' => (int) $r->get('id'), 'created' => 0 === $id, 'fields' => $known];
    }
}
