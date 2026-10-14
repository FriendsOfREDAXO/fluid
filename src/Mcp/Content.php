<?php

namespace FriendsOfRedaxo\Fluid\Mcp;

use FriendsOfRedaxo\Fluid\Activity;
use FriendsOfRedaxo\Fluid\Api;
use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\Blog\Blog;
use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\BlockOps;
use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\Form\FieldForm;
use FriendsOfRedaxo\Fluid\Layout;
use FriendsOfRedaxo\Fluid\PageTemplates;
use FriendsOfRedaxo\Fluid\RichText;
use FriendsOfRedaxo\Fluid\Structure;
use FriendsOfRedaxo\Fluid\Versions;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;

use function in_array;
use function is_array;
use function is_string;

/**
 * Seiten und Blöcke für den MCP-Server – dieselben Wege wie der Editor auf der Website (Entwurf, Versionen, Aktivität).
 * Prüft Rechte über Access; Fehler als ApiError (Meldung geht an den Client).
 */
final class Content
{
    public function __construct(private readonly Access $access) {}

    /** Adressen absolut (Werkzeuge außerhalb der Website können mit /pfad nichts anfangen) */
    public static function abs(string $url): string
    {
        if (str_starts_with($url, 'http') || !\Redaxo\Core\Core::hasRequest()) {
            return $url;
        }

        return \Redaxo\Core\Core::getRequest()->getSchemeAndHttpHost() . $url;
    }

    public function language(mixed $lang): int
    {
        if (is_string($lang) && '' !== $lang) {
            foreach (Language::getAll() as $l) {
                if ($l->code === strtolower($lang)) {
                    return $l->id;
                }
            }
            throw new ApiError('Unbekannte Sprache „' . $lang . '“.');
        }

        return Language::getStartId();
    }

    /** Seite aus ID, Pfad/URL oder „home“ */
    public function article(mixed $ref, int $lang): Article
    {
        $id = match (true) {
            is_int($ref), is_string($ref) && ctype_digit($ref) => (int) $ref,
            in_array($ref, [null, '', 'home', '/'], true) => Article::getSiteStartArticleId(),
            default => \FriendsOfRedaxo\Fluid\Routing\Routes::find(trim((string) parse_url((string) $ref, PHP_URL_PATH), '/'), $lang),
        };
        $a = null !== $id ? Article::get((int) $id, $lang) : null;
        if (null === $a || !$this->access->canReadPage($a)) {
            throw new ApiError('Seite nicht gefunden.');
        }

        return $a;
    }

    /** @return array<string, mixed> */
    public function pageInfo(Article $a): array
    {
        $out = ['id' => $a->id, 'name' => $a->name, 'url' => self::abs($a->getUrl()), 'online' => $a->isOnline(), 'language' => Language::get($a->languageId)?->code];
        if (!$this->access->isPublic()) {
            $out['draft_changes'] = Versions::isWorkVersion() && $this->hasDraft($a);
            $out['template'] = $a->templateKey;
        }
        if (null !== $blog = Blog::blogOf($a)) {
            $out['blog'] = $blog;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> Seitenbaum (öffentlich: nur online) */
    public function tree(int $lang): array
    {
        $filter = function (array $nodes) use (&$filter, $lang): array {
            $out = [];
            foreach ($nodes as $n) {
                $a = Article::get((int) $n['id'], $lang);
                if (null === $a || !$this->access->canReadPage($a)) {
                    continue;
                }
                $item = ['id' => $n['id'], 'name' => $n['name'], 'url' => self::abs((string) $n['url']), 'online' => $n['online'], 'menu' => $n['menu']];
                if ($n['blog'] ?? false) {
                    $item['blog'] = true;
                }
                if ([] !== $kids = $filter($n['children'] ?? [])) {
                    $item['children'] = $kids;
                }
                $out[] = $item;
            }

            return $out;
        };

        return $filter(Structure::tree($lang));
    }

    /**
     * Blöcke einer Seite. Öffentlich immer die veröffentlichte Fassung ohne Offline-Blöcke, sonst Entwurf (Standard) oder live.
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(Article $a, bool $live = false): array
    {
        $public = $this->access->isPublic();
        $revision = $public || $live ? ArticleRevision::LIVE : FluidAddon::revision();
        $rows = Sql::factory()->getArray(
            'SELECT * FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ?' . ($public ? ' AND status = 1' : '') . ' ORDER BY ctype_id, priority',
            [$a->id, $a->languageId, $revision],
        );
        $out = [];
        foreach ($rows as $r) {
            $m = Module::get((string) $r['module']);
            $tunes = BlockModule::decode((string) $r['value2']);
            $b = ['id' => (int) $r['id'], 'type' => (string) $r['module'], 'label' => $m instanceof BlockModule ? (string) ($m->definition()['label'] ?? $m->name) : ($m->name ?? (string) $r['module'])];
            if (($tunes['col'] ?? 0) > 0) {
                $b['column'] = (int) $tunes['col'];
            }
            if (!$public) {
                $b['online'] = 1 === (int) $r['status'];
            }
            $b['data'] = BlockModule::decode((string) $r['value1']);
            if (!$public && [] !== $tunes) {
                $b['options'] = $tunes;
            }
            $out[] = $b;
        }

        return $out;
    }

    /** @return array<string, array<string, mixed>> Blocktypen mit Feldern (für add_block/update_block) */
    public function blockTypes(?string $only = null): array
    {
        $out = [];
        foreach (Module::getAll() as $m) {
            if (!$m instanceof BlockModule || !$m->available() || (null !== $only && $only !== $m->key && $only !== $m->type())) {
                continue;
            }
            $d = $m->definition();
            $out[$m->key] = array_filter([
                'label' => (string) ($d['label'] ?? $m->name),
                'group' => (string) ($d['group'] ?? ''),
                'help' => (string) ($d['help'] ?? ''),
                'layout' => $m instanceof \FriendsOfRedaxo\Fluid\LayoutBlock ? true : null,
                'variants' => $m->variants() ?: null,
                'fields' => self::fieldSchema($m->fields()),
            ], static fn ($v) => null !== $v && '' !== $v);
        }

        return $out;
    }

    /**
     * Felder in knapper Form: name, type, label, required, options, fields (Wiederholgruppe).
     *
     * @param array<mixed> $fields
     * @return list<array<string, mixed>>
     */
    private static function fieldSchema(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $f) {
            if (!is_array($f)) {
                continue;
            }
            $name = (string) ($f['name'] ?? $key);
            $item = ['name' => $name, 'type' => (string) ($f['type'] ?? 'text'), 'label' => (string) ($f['label'] ?? $name)];
            foreach (['required', 'help', 'max', 'default'] as $k) {
                if (isset($f[$k]) && '' !== $f[$k]) {
                    $item[$k] = $f[$k];
                }
            }
            if (isset($f['options']) && is_array($f['options'])) {
                $item['options'] = $f['options'];
            }
            if ('richtext' === $item['type']) {
                $item['format'] = 'HTML (p, h2–h4, ul/ol/li, a, strong, em) – keine Bilder';
            }
            if ('media' === $item['type']) {
                $item['format'] = 'Dateiname aus dem Medienpool';
            }
            if (isset($f['fields']) && is_array($f['fields'])) {
                $item['fields'] = self::fieldSchema($f['fields']);
            }
            $out[] = $item;
        }

        return $out;
    }

    // ------------------------------------------------------------------ Schreiben (immer in den Entwurf)

    private function writable(Article $a): void
    {
        if (!$this->access->canWritePage($a)) {
            throw new ApiError('Dieser Zugang darf die Seite „' . $a->name . '“ nicht ändern.');
        }
    }

    private function slice(Article $a, int $id): ArticleSlice
    {
        $s = ArticleSlice::getArticleSliceById($id, $a->languageId, FluidAddon::revision());
        if (null === $s || $s->articleId !== $a->id) {
            throw new ApiError('Block ' . $id . ' gibt es auf dieser Seite nicht (IDs mit get_page holen).');
        }

        return $s;
    }

    private function module(string $key): BlockModule
    {
        $m = Module::get($key);
        if (!$m instanceof BlockModule) {
            throw new ApiError('Unbekannter Blocktyp „' . $key . '“ (siehe list_block_types).');
        }
        if (!$this->access->canModule($key)) {
            throw new ApiError('Dieser Zugang darf den Block „' . $m->name . '“ nicht bearbeiten.');
        }

        return $m;
    }

    /**
     * Nur bekannte Felder übernehmen, Rich-Text durch denselben Filter wie die Ausgabe.
     *
     * @param array<mixed> $fields
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private static function clean(array $fields, array $data): array
    {
        $out = [];
        foreach ($fields as $key => $f) {
            if (!is_array($f)) {
                continue;
            }
            $name = (string) ($f['name'] ?? $key);
            if (!array_key_exists($name, $data)) {
                continue;
            }
            $v = $data[$name];
            $type = (string) ($f['type'] ?? 'text');
            if ('repeater' === $type && is_array($v) && isset($f['fields']) && is_array($f['fields'])) {
                $v = array_values(array_map(static fn ($row) => is_array($row) ? self::clean($f['fields'], $row) : [], $v));
            } elseif ('richtext' === $type) {
                $v = RichText::html(is_scalar($v) ? (string) $v : '');
            } elseif (is_array($v)) {
                $v = array_map(static fn ($x) => is_scalar($x) ? (string) $x : '', $v);
            } elseif ('bool' === $type) {
                $v = (bool) $v;
            } else {
                $v = is_scalar($v) ? mb_substr(strip_tags((string) $v), 0, 20000) : '';
            }
            $out[$name] = $v;
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    public function updateBlock(Article $a, int $id, array $data, bool $replace): array
    {
        $this->writable($a);
        $s = $this->slice($a, $id);
        $m = $this->module($s->moduleKey);
        $old = BlockModule::decode($s->getValue(1));
        $new = self::clean($m->fields(), $data);
        if ([] === $new) {
            throw new ApiError('Keine bekannten Felder in „data“ (Felder des Blocktyps mit list_block_types).');
        }
        $merged = $replace ? FieldForm::withDefaults($m->fields(), $new, $m->variants()) + (isset($old['widths']) ? ['widths' => $old['widths']] : []) : array_replace($old, $new);
        $this->store($a, $s->id, $merged, null, $m->name);

        return ['block' => $id, 'changed' => array_keys($new)];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $tunes
     */
    private function store(Article $a, int $id, array $data, ?array $tunes, string $label): void
    {
        $sql = Sql::factory()->setTable('rex_article_slice')
            ->setWhere(['id' => $id, 'article_id' => $a->id, 'revision' => FluidAddon::revision()])
            ->setValue('value1', json_encode($data, JSON_UNESCAPED_UNICODE));
        if (null !== $tunes) {
            $sql->setValue('value2', json_encode((object) $tunes, JSON_UNESCAPED_UNICODE));
        }
        $sql->addGlobalUpdateFields($this->access->user->login ?? 'mcp');
        $sql->update();
        ArticleCache::delete($a->id, $a->languageId);
        Versions::snapshot($a->id, $a->languageId, FluidAddon::revision(), 'fluid_save');
        Activity::log('save', $a->id, $label . ' (MCP)');
    }

    /** @param array<string, mixed> $data */
    public function addBlock(Article $a, string $type, array $data, int $after): array
    {
        $this->writable($a);
        $m = $this->module($type);
        if ($after < 0) {
            // ans Ende: hinter die letzte Einheit
            $after = (int) (Sql::factory()->getArray('SELECT id FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY priority DESC LIMIT 1',
                [$a->id, $a->languageId, FluidAddon::revision()])[0]['id'] ?? 0);
        }
        $values = FieldForm::withDefaults($m->fields(), self::clean($m->fields(), $data), $m->variants());
        $tunes = isset($m->definition()['background']) ? ['background' => $m->definition()['background']] : [];
        $id = BlockOps::insert($a, $this->access->user, $after, ['module' => $m->key, 'value1' => json_encode($values, JSON_UNESCAPED_UNICODE), 'value2' => json_encode((object) $tunes)]);
        Versions::snapshot($a->id, $a->languageId, FluidAddon::revision(), 'fluid_save');
        Activity::log('add', $a->id, $m->name . ' (MCP)');

        return ['block' => $id];
    }

    public function deleteBlock(Article $a, int $id): array
    {
        $this->writable($a);
        $s = $this->slice($a, $id);
        $this->module($s->moduleKey);
        Versions::snapshot($a->id, $a->languageId, FluidAddon::revision(), 'fluid_save');
        ContentHandler::deleteSlice($s->id);   // Spalten-Blöcke eines Layouts räumt FluidAddon (SLICE_DELETE) mit ab
        ArticleCache::delete($a->id, $a->languageId);
        Activity::log('delete', $a->id, (Module::get($s->moduleKey)->name ?? $s->moduleKey) . ' (MCP)');

        return ['deleted' => $id, 'layout' => Layout::isLayout($s)];
    }

    public function moveBlock(Article $a, int $id, int $before): array
    {
        $this->writable($a);
        if (!$this->access->can('moveSlice[]')) {
            throw new ApiError('Dieser Zugang darf Blöcke nicht verschieben.');
        }
        $this->slice($a, $id);
        BlockOps::reorder($a, $id, $before);
        Activity::log('move', $a->id, 'Block ' . $id . ' (MCP)');

        return ['moved' => $id];
    }

    public function setOnline(Article $a, int $id, bool $online): array
    {
        $this->writable($a);
        if (!$this->access->can('publishSlice[]')) {
            throw new ApiError('Dieser Zugang darf Blöcke nicht ein- und ausschalten.');
        }
        $this->slice($a, $id);
        ContentHandler::sliceStatus($id, $online ? 1 : 0);

        return ['block' => $id, 'online' => $online];
    }

    public function publish(Article $a): array
    {
        $this->writable($a);
        if (!$this->access->publish) {
            throw new ApiError('Dieser Zugang darf nicht veröffentlichen – die Änderungen liegen im Entwurf; ein Mensch veröffentlicht sie auf der Website.');
        }
        if (!Versions::isWorkVersion()) {
            return ['published' => true, 'note' => 'Ohne Arbeitsversion sind Änderungen sofort live.'];
        }
        Api::publish($a);

        return ['published' => true, 'url' => self::abs($a->getUrl())];
    }

    /** Neue Seite (offline) unter einem Menüpunkt (0/leer = oberste Ebene); menu = als Menüpunkt (Kategorie) */
    public function createPage(string $name, int $parent, bool $menu, int $lang, int $template = 0): array
    {
        if (!$this->access->write) {
            throw new ApiError('Dieser Zugang darf nur lesen.');
        }
        $name = trim(mb_substr(strip_tags($name), 0, 190));
        if ('' === $name) {
            throw new ApiError('Bitte einen Titel angeben.');
        }
        $parentId = $parent > 0 ? $parent : null;
        $perm = $this->access->user->getComplexPerm('structure');
        if (!$this->access->user->admin && !($perm instanceof \Redaxo\Core\Content\StructurePermission && $perm->hasCategoryPerm($parentId))) {
            throw new ApiError('Keine Berechtigung, hier eine Seite anzulegen.');
        }
        if (!$this->access->can($menu ? 'addCategory[]' : 'addArticle[]')) {
            throw new ApiError('Dieser Zugang darf keine ' . ($menu ? 'Menüpunkte' : 'Seiten') . ' anlegen.');
        }
        $start = Article::get(Article::getSiteStartArticleId(), $lang);
        $tpl = null !== $start ? (string) $start->templateKey : '';
        if ($menu) {
            CategoryHandler::addCategory($parentId, ['catpriority' => 999, 'catname' => $name, 'status' => 0]);
        } else {
            ArticleHandler::addArticle(['category_id' => $parentId, 'priority' => 999, 'name' => $name, 'template' => $tpl]);
        }
        $id = (int) Sql::factory()->getArray('SELECT MAX(id) AS id FROM rex_article')[0]['id'];
        ArticleHandler::editArticle($id, $lang, ['name' => $name, 'template' => $tpl]);
        if (null !== $parentId && !$menu && Blog::isBlog($parentId) && ($new = Article::get($id, $lang))) {
            Blog::saveMeta($new, date('Y-m-d'), $this->access->user->name ?: $this->access->user->login, '');
        }
        if ($template > 0 && PageTemplates::isTemplate($template)) {
            PageTemplates::copy($template, $id, $lang);
        }
        Activity::log('page_add', $id, $name . ' (MCP)');

        return ['id' => $id, 'name' => $name, 'online' => false, 'note' => 'Seite ist offline angelegt; online schaltet ein Mensch (oder ein Zugang mit Veröffentlichen-Recht über die Seiteneinstellungen).'];
    }

    private function hasDraft(Article $a): bool
    {
        $sig = static fn (int $rev): string => md5((string) json_encode(Sql::factory()->getArray(
            'SELECT module, value1, value2, status FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY ctype_id, priority',
            [$a->id, $a->languageId, $rev],
        )));

        return $sig(ArticleRevision::WORK) !== $sig(ArticleRevision::LIVE);
    }
}
