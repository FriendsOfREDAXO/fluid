<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\ExtensionPoint\ArticleUsageCheck;
use Redaxo\Core\ExtensionPoint\Extension;

/**
 * Kategorie „Seitenvorlagen“ schützen: nicht löschbar, solange fluid installiert ist (der Schutz hängt an diesem
 * Extension Point – ohne fluid ist sie eine gewöhnliche Kategorie). Wer sie im Backend sieht, regelt das Strukturrecht
 * des Cores; auf der Website (Seitenbaum, Suche, Entwürfe) blendet fluid sie aus, verwaltet wird dort im Dialog „Vorlagen …“.
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
    }
}
