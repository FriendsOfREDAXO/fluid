<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\ExtensionPoint\ArticleUsageCheck;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;

use function in_array;
use function preg_replace;
use function str_starts_with;

/**
 * Kategorie „Seitenvorlagen“ schützen: nicht löschbar, solange fluid installiert ist (der Schutz hängt an diesem
 * Extension Point – ohne fluid ist sie eine gewöhnliche Kategorie), und im Backend nur für Admins sichtbar. Vorlagen
 * verwalten alle Berechtigten im Dialog „Vorlagen …“ auf der Website.
 */
final class PageTemplatesGuard
{
    public static function boot(): void
    {
        Extension::register(ArticleUsageCheck::class, static function (ArticleUsageCheck $ep): void {
            if (null !== PageTemplates::categoryId() && $ep->article->id === PageTemplates::categoryId()) {
                $ep->usages->add('fluid', 'Seitenvorlagen der Website – lässt sich erst löschen, wenn fluid deinstalliert ist');
            }
        });

        $user = Core::getUser();
        $cat = PageTemplates::categoryId();
        if (!Core::isBackend() || null === $user || $user->admin || null === $cat) {
            return;
        }
        $page = Request::get('page', 'string', '');
        $structure = 'structure' === $page || str_starts_with($page, 'content') || 'linkmap' === $page;
        if (!$structure) {
            return;
        }
        // direkt aufgerufen (Adresse, Lesezeichen): zurück zur Struktur
        $article = Request::request('article_id', 'int', 0);
        if (Request::request('category_id', 'int', 0) === $cat || ($article > 0 && PageTemplates::isTemplate($article))) {
            Response::sendRedirect('linkmap' === $page
                ? Url::backendPage('linkmap', ['picker' => Request::request('picker', 'string', ''), 'language' => Request::request('language', 'int', 0)])
                : Url::backendPage('structure'));
        }
        // Listen: die Zeile der Kategorie (Struktur) bzw. ihr Eintrag (Linkmap) entfällt
        Extension::register('OUTPUT_FILTER', static function (ExtensionPoint $ep) use ($cat, $page): string {
            $html = (string) $ep->subject;
            $id = '(?:\?|&amp;|&)category_id=' . $cat . '(?=["&])';
            $html = in_array($page, ['structure', 'linkmap'], true)
                ? (string) preg_replace(['~<tr\b[^>]*>(?:(?!</tr>).)*?' . $id . '(?:(?!</tr>).)*</tr>\s*~s', '~<li\b[^>]*>\s*<a\b[^>]*' . $id . '(?:(?!</li>).)*</li>\s*~s'], '', $html)
                : $html;

            return $html;
        });
    }
}
