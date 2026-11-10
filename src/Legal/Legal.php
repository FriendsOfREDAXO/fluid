<?php

namespace FriendsOfRedaxo\Fluid\Legal;

use FriendsOfRedaxo\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Language\Language;

use function preg_match;
use function trim;

/**
 * Rechtstexte zentral (aus KLXM Studio – Core\Legal): Impressum, Datenschutzerklärung und Erklärung zur
 * Barrierefreiheit aus Website → Einstellungen → „Recht & Suchmaschinen“, in der Sprache der aufgerufenen Seite.
 * Für Kits, Blöcke und AddOns – statt eigener Helfer. Ohne Einstellung gilt für den Datenschutz eine Seite der
 * obersten Ebene, deren Name mit „Datenschutz“ bzw. „Privacy“ beginnt.
 *
 *     Legal::url('privacy')   Legal::links()   Legal::article('imprint')
 *
 * Optional: Datenschutzhinweise an Formularen im Dialog (LegalDialog) – `Legal::$config = new LegalConfig(dialog: true)`.
 */
final class Legal
{
    /** Art => Schlüssel der Einstellung */
    public const array KEYS = ['imprint' => 'imprint_page', 'privacy' => 'privacy_page', 'accessibility' => 'accessibility_page'];

    /** Art => Bezeichnung (Quelle Deutsch, übersetzt mit lt() des Kits) */
    public const array LABELS = ['imprint' => 'Impressum', 'privacy' => 'Datenschutz', 'accessibility' => 'Barrierefreiheit'];

    /** Einstellungen des Projekts (Project::configure()); null = Vorgaben */
    public static ?LegalConfig $config = null;

    public static function config(): LegalConfig
    {
        return self::$config ??= new LegalConfig();
    }

    /** Seite einer Art in der aktuellen Sprache (nur online) – oder null */
    public static function article(string $type): ?Article
    {
        $value = trim((string) Settings::get(self::KEYS[$type] ?? '', ''));
        $article = preg_match('~^(?:redaxo://|page:)?(\d+)$~', $value, $m) ? Article::get((int) $m[1], Language::getCurrentId()) : null;
        if (null === $article && '' === $value && 'privacy' === $type) {
            foreach (Article::getRootArticles(true) as $a) {
                if (preg_match('~^(datenschutz|privacy)~i', $a->name)) {
                    $article = $a;
                    break;
                }
            }
        }

        return null !== $article && $article->isOnline() ? $article : null;
    }

    /** Adresse einer Art – eine Seite der Website oder eine eingetragene Adresse (z. B. https://…) – oder '' */
    public static function url(string $type): string
    {
        $article = self::article($type);
        if (null !== $article) {
            return $article->getUrl();
        }
        $value = trim((string) Settings::get(self::KEYS[$type] ?? '', ''));

        return preg_match('~^(https?://|/)~', $value) ? $value : '';
    }

    /**
     * Links Impressum, Datenschutz, Barrierefreiheit (nur gesetzte).
     *
     * @return list<array{type: string, label: string, href: string}>
     */
    public static function links(): array
    {
        $out = [];
        foreach (self::LABELS as $type => $label) {
            $href = self::url($type);
            if ('' !== $href) {
                $out[] = ['type' => $type, 'label' => \function_exists('lt') ? lt($label) : $label, 'href' => $href];
            }
        }

        return $out;
    }
}
