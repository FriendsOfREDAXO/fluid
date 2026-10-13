<?php

namespace FriendsOfRedaxo\Fluid;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Asset;

final class FluidAddon extends Addon
{
    #[Override]
    public function install(): void
    {
        // Seiteneinstellungen auf der Website (PageSettings)
        Table::get(PageSettings::TABLE)
            ->ensureColumn(new Column('article_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('language_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('description', 'text', true))
            ->ensureColumn(new Column('image', 'varchar(255)', true))
            ->ensureColumn(new Column('noindex', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('title', 'varchar(191)', true))
            // Blog: Kategorie ist ein Blog (blog) bzw. Angaben eines Beitrags
            ->ensureColumn(new Column('blog', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('post_date', 'date', true))
            ->ensureColumn(new Column('author', 'varchar(120)', true))
            ->ensureColumn(new Column('tags', 'varchar(255)', true))
            ->setPrimaryKey(['article_id', 'language_id'])
            ->ensure();

        // Schöne Adressen, Domains, Weiterleitungen (Routing\*)
        Table::get(Routing\Routes::TABLE)
            ->ensureColumn(new Column('article_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('language_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('slug', 'varchar(191)'))
            ->ensureColumn(new Column('path', 'varchar(500)'))
            ->setPrimaryKey(['article_id', 'language_id'])
            ->ensureIndex(new \Redaxo\Core\Database\Index('lang_path', ['language_id', 'path']))
            ->ensure();
        Table::get(Routing\Domains::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('host', 'varchar(191)'))
            ->ensureColumn(new Column('role', 'varchar(10)', false, 'alias'))
            ->ensureColumn(new Column('article_id', 'int(10) unsigned', true))
            ->ensureColumn(new Column('subtree', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('canonical', 'varchar(10)', false, 'own'))
            ->ensureColumn(new Column('note', 'varchar(191)', true))
            ->ensure();
        Table::get(Routing\Redirects::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('source', 'varchar(500)'))
            ->ensureColumn(new Column('source_hash', 'varchar(40)'))
            ->ensureColumn(new Column('wildcard', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('target', 'varchar(500)'))
            ->ensureColumn(new Column('code', 'smallint(3)', false, '301'))
            ->ensureColumn(new Column('note', 'varchar(191)', true))
            ->ensureColumn(new Column('origin', 'varchar(10)', false, 'manual'))
            ->ensureColumn(new Column('hits', 'int(10) unsigned', false, '0'))
            ->ensureColumn(new Column('last_hit', 'datetime', true))
            ->ensureColumn(new Column('active', 'tinyint(1)', false, '1'))
            ->ensureColumn(new Column('created', 'datetime', true))
            ->ensureColumn(new Column('updated', 'datetime', true))
            ->ensureIndex(new \Redaxo\Core\Database\Index('source_hash', ['source_hash']))
            ->ensure();
        Table::get(Routing\Redirects::TABLE_404)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('path', 'varchar(500)'))
            ->ensureColumn(new Column('path_hash', 'varchar(40)'))
            ->ensureColumn(new Column('hits', 'int(10) unsigned', false, '1'))
            ->ensureColumn(new Column('first_seen', 'datetime', true))
            ->ensureColumn(new Column('last_seen', 'datetime', true))
            ->ensureIndex(new \Redaxo\Core\Database\Index('path_hash', ['path_hash']))
            ->ensure();
        Table::get(Activity::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('created', 'datetime', true))
            ->ensureColumn(new Column('user', 'varchar(191)', true))
            ->ensureColumn(new Column('action', 'varchar(40)'))
            ->ensureColumn(new Column('article_id', 'int(10) unsigned', true))
            ->ensureColumn(new Column('detail', 'varchar(191)', true))
            ->ensure();
        Table::get(Data\Inbox::TABLE)
            ->ensureColumn(new Column('table_name', 'varchar(191)'))
            ->ensureColumn(new Column('dataset_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('state', 'varchar(10)', false, 'new'))
            ->ensureColumn(new Column('updated', 'datetime', true))
            ->ensureColumn(new Column('user', 'varchar(191)', true))
            ->setPrimaryKey(['table_name', 'dataset_id'])
            ->ensure();
        \Redaxo\Core\Content\ArticleSliceHistory::checkTables();
        Routing\Routes::rebuild();
    }

    /**
     * Deinstallieren: Tabellen, Einstellungen, Suchindex und Cache von fluid entfernen. Inhalte (Slices der Blöcke) und
     * Artikel bleiben – sie gehören dem Core; ohne fluid fehlen nur die Module, die sie ausgeben.
     */
    #[Override]
    public function uninstall(): void
    {
        foreach ([PageSettings::TABLE, Routing\Routes::TABLE, Routing\Domains::TABLE, Routing\Redirects::TABLE, Routing\Redirects::TABLE_404, Activity::TABLE, Data\Inbox::TABLE] as $t) {
            Table::get($t)->drop();
        }
        \Redaxo\Core\Config::removeNamespace($this->name);
        \Redaxo\Core\Filesystem\Dir::delete($this->getDataPath());
        \Redaxo\Core\Filesystem\Dir::delete($this->getCachePath());
    }

    /** Backend: AddOns → fluid (Status, Hilfe) – nur Admins */
    #[Override]
    public function getPages(): iterable
    {
        $main = new \Redaxo\Core\Backend\MainPage('addons', 'fluid', 'fluid');
        $main->setIcon('rex-icon fa-pen-to-square');
        $main->setRequiredPermissions('admin');
        $main->addSubpage(new \Redaxo\Core\Backend\Page('status', 'Status'));
        $main->addSubpage(new \Redaxo\Core\Backend\Page('help', 'Hilfe'));
        if (!Core::getUser()?->admin) {
            $main->setHidden();
        }

        yield $main;
    }

    #[Override]
    public function boot(): void
    {
        if (Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'content')) {
            Asset::addCssFile($this->getAssetsUrl('form.css'));
            Asset::addCssFile($this->getAssetsUrl('rte.css'));
            Asset::addJsFile($this->getAssetsUrl('form.js'));
            Asset::addJsFile($this->getAssetsUrl('rte.js'));
            // Absprung „Auf der Website bearbeiten“ über den Slices – nur bei Artikeln mit Block-Template
            Extension::register('STRUCTURE_CONTENT_BEFORE_SLICES', static function (ExtensionPoint $ep): string {
                $article = Article::get((int) $ep->getParam('article_id'), (int) $ep->getParam('language'));
                $template = $article?->templateKey ? Template::get($article->templateKey) : null;
                $blocks = array_filter(Module::getAll(), static fn (Module $m) => $m instanceof BlockModule && $template && Template::checkModuleAllowed($template->key, 1, $m->key));
                if (!$article || !$blocks) {
                    return (string) $ep->subject;
                }
                $url = Url::article($article->id, $article->languageId, [Editing::PARAM => 1]);

                return (string) $ep->subject . Links::json($article->languageId) . '<p class="blocks-frontend-link"><a class="btn btn-primary" href="' . $url . '" target="_blank" rel="noopener">'
                    . '<i class="rex-icon fa-pen-to-square"></i> Auf der Website bearbeiten</a></p>';
            });
        }

        // Schöne Adressen (URL_REWRITE, Strukturänderungen; im Frontend: Adresse → Seite)
        // Untertitel: PHP erkennt .srt als application/x-subrip, die Liste des Cores erwartet nur text/plain
        $mime = &\Redaxo\Core\MediaPool\MediaPool::$allowedMimeTypes;
        if ($mime) {
            $mime['srt'] = array_values(array_unique([...($mime['srt'] ?? []), 'text/plain', 'application/x-subrip', 'application/x-srt']));
            $mime['vtt'] = array_values(array_unique([...($mime['vtt'] ?? []), 'text/vtt', 'text/plain']));
        }
        unset($mime);
        Routing\Router::boot();

        // Layout gelöscht (Backend oder Website): seine Spalten-Blöcke werden wieder normale Abschnitte
        Extension::register('SLICE_DELETE', static function (ExtensionPoint $ep): void {
            $slice = ArticleSlice::getArticleSliceById((int) $ep->getParam('slice_id'), (int) $ep->getParam('language_id'), (int) $ep->getParam('slice_revision'));
            if (!$slice || !Layout::isLayout($slice)) {
                return;
            }
            $sql = Sql::factory();
            foreach (Layout::children($slice) as $child) {
                $tunes = BlockModule::decode($child->getValue(2));
                unset($tunes['col']);
                $sql->setQuery('UPDATE rex_article_slice SET value2 = ? WHERE id = ?', [json_encode((object) $tunes, JSON_UNESCAPED_UNICODE), $child->id]);
            }
        });

        if (!Core::isFrontend()) {
            return;
        }

        // Endpunkte des Editors (?fluid_api=…): vor der Ausgabe der Seite beantworten
        if ('' !== Request::get(Api::PARAM, 'string')) {
            Extension::register('FE_OUTPUT', static fn () => Api::handle());
        }

        // Bearbeiten-Modus: Artikel live aus der Datenbank (Arbeitsversion, sofern eingeschaltet) statt aus dem Cache
        Extension::register('ART_INIT', static function (ExtensionPoint $ep): void {
            if (!Editing::requested()) {
                return;
            }
            Editing::start();
            $article = Type::instanceOf($ep->subject, ArticleContent::class);
            $article->eval = true;
            $article->sliceRevision = self::revision();
        });

        // Im Bearbeiten-Modus auch Offline-Blöcke zeigen (gekennzeichnet), damit sie sich wieder einschalten lassen
        Extension::register('ART_SLICES_QUERY', static function (ExtensionPoint $ep): ?string {
            if (!Editing::active()) {
                return null;
            }

            return str_replace(' AND rex_article_slice.status = 1', '', (string) $ep->subject);
        }, ExtensionLevel::Late);

        // Leiste und Editor nur für angemeldete Redaktion – Besucher bekommen kein zusätzliches Markup
        Extension::register('OUTPUT_FILTER', static function (ExtensionPoint $ep): string {
            $html = (string) $ep->subject;
            // Redaktionskommentare [# … #] (wie KLXM Studio): für Besucher und in der Ansicht unsichtbar, im Bearbeiten-Modus markiert (editor.js)
            if (!Editing::active() && str_contains($html, '[#')) {
                $html = (string) preg_replace_callback('~<main\b.*?</main>~s', static fn (array $m) => (string) preg_replace('~\s?\[#.*?#\]~s', '', $m[0]), $html);
            }
            if (null === Editing::user()) {
                return $html;
            }

            return Toolbar::inject($html);
        }, ExtensionLevel::Late);
    }

    /** Revision, in der auf der Website bearbeitet wird: Arbeitsversion, wenn eingeschaltet – sonst live */
    public static function revision(): int
    {
        return Core::getConfig('article_work_version', false) ? ArticleRevision::WORK : ArticleRevision::LIVE;
    }
}
