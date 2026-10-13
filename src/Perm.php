<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Security\User;

/**
 * Aktionsrechte des Cores (Benutzer → Rollen → „Optionen“) auf der Website – dieselben Rechte wie im Backend:
 * wer dort keine Artikel löschen darf, darf es auch auf der Website nicht. Admins dürfen alles.
 */
final class Perm
{
    /** Bezeichnungen für Meldungen */
    private const array LABEL = [
        'version[live_version]' => 'Veröffentlichen (Liveversion)',
        'moveSlice[]' => 'Blöcke verschieben',
        'publishSlice[]' => 'Blöcke online/offline schalten',
        'copyContent[]' => 'Inhalte kopieren',
        'addArticle[]' => 'Artikel anlegen',
        'addCategory[]' => 'Kategorien anlegen',
        'editArticle[]' => 'Artikel bearbeiten',
        'editCategory[]' => 'Kategorien bearbeiten',
        'moveArticle[]' => 'Artikel verschieben',
        'moveCategory[]' => 'Kategorien verschieben',
        'publishArticle[]' => 'Artikel online/offline schalten',
        'publishCategory[]' => 'Kategorien online/offline schalten',
        'deleteArticle[]' => 'Artikel löschen',
        'deleteCategory[]' => 'Kategorien löschen',
        'article2category[]' => 'Artikel ↔ Kategorie umwandeln',
    ];

    public static function can(User $user, string $perm): bool
    {
        return $user->admin || $user->hasPerm($perm);
    }

    public static function require(User $user, string $perm): void
    {
        if (!self::can($user, $perm)) {
            throw new ApiError('Dafür fehlt Ihnen das Recht „' . (self::LABEL[$perm] ?? $perm) . '“.');
        }
    }

    /** Recht je nach Art der Seite: Menüseite (Kategorie) oder Seite (Artikel), z. B. for($a, 'move') */
    public static function forPage(Article $a, string $action): string
    {
        $isCat = $a->isStartArticle() && null !== Category::get($a->id);

        return $action . ($isCat ? 'Category' : 'Article') . '[]';
    }

    /** Für die Leiste: was darf dieser Benutzer? */
    public static function flags(User $user): array
    {
        $out = [];
        foreach (array_keys(self::LABEL) as $p) {
            $out[preg_replace('~\[.*$~', '', $p)] = self::can($user, $p);
        }
        $out['publish'] = $out['version'];
        unset($out['version']);

        return $out;
    }
}
