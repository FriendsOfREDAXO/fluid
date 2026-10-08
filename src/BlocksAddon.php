<?php

namespace FriendsOfRedaxo\Blocks;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Asset;

final class BlocksAddon extends Addon
{
    #[Override]
    public function boot(): void
    {
        if (Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'content')) {
            Asset::addCssFile($this->getAssetsUrl('form.css'));
            Asset::addJsFile($this->getAssetsUrl('form.js'));
        }

        if (!Core::isFrontend()) {
            return;
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

        // Leiste und Editor nur für angemeldete Redaktion – Besucher bekommen kein zusätzliches Markup
        Extension::register('OUTPUT_FILTER', static function (ExtensionPoint $ep): string {
            $html = (string) $ep->subject;
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
