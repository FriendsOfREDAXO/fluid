<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Core;

use function array_key_exists;
use function is_array;

/**
 * Website-Einstellungen (Modell aus KLXM Studio – „Website“): Name, Logo, Kontakt, Öffnungszeiten, Social Media,
 * Hinweisleiste, Kopf und Fuß, Rechtliches. Zentral gepflegt, erscheinen sie automatisch überall (Template, Blöcke
 * „Kontakt“ und „Karte“, strukturierte Daten). Gespeichert in der Config des AddOns ('site'); Kits lesen sie über setting().
 *
 * Felder im Format der Kits (FieldForm) – je Reiter eine Gruppe. Domains und Weiterleitungen haben eigene Reiter (Routing).
 */
final class Settings
{
    public const string CONFIG = 'site';

    /** @var array<string, mixed>|null */
    private static ?array $values = null;

    /** @return array<string, array{label: string, fields: list<array<string, mixed>>}> */
    public static function schema(): array
    {
        return self::$schema ??= (array) \Redaxo\Core\ExtensionPoint\Extension::dispatch(new \Redaxo\Core\ExtensionPoint\ExtensionPoint('FLUID_SETTINGS_SCHEMA', self::baseSchema()));
    }

    /** @var array<string, array{label: string, fields: list<array<string, mixed>>}>|null */
    private static ?array $schema = null;

    /** @return array<string, array{label: string, fields: list<array<string, mixed>>}> */
    private static function baseSchema(): array
    {
        // Reiter „Kit“ nur, wenn es mehr als eins gibt (Projekt oder Composer-Pakete mit #[AsKit])
        $kits = Kit::options();
        $kitTab = \count($kits) > 1 ? ['kit' => ['label' => 'Kit', 'fields' => [
            ['name' => 'kit', 'label' => 'Kit der Website', 'type' => 'select', 'required' => true, 'default' => Kit::DEFAULT, 'options' => $kits,
                'help' => 'Das Kit bestimmt die Gestaltung der ganzen Website. Inhalte und Blöcke bleiben beim Wechsel erhalten; jedes Kit hat eigene Design-Einstellungen.'],
        ]]] : [];

        $days = ['1' => 'Montag', '2' => 'Dienstag', '3' => 'Mittwoch', '4' => 'Donnerstag', '5' => 'Freitag', '6' => 'Samstag', '0' => 'Sonntag'];

        return [
            'base' => ['label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name (Organisation, Firma, Praxis …)', 'type' => 'text', 'max' => 120, 'required' => true,
                    'help' => 'Erscheint im Kopf, im Fuß, im Browser-Tab und für Suchmaschinen.'],
                ['name' => 'short_name', 'label' => 'Kurzname (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half'],
                ['name' => 'tagline', 'translate' => true, 'label' => 'Claim / Unterzeile (optional)', 'type' => 'text', 'max' => 120, 'width' => 'half'],
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'help' => 'SVG oder PNG mit transparentem Hintergrund. Ohne Logo erscheint der Name.'],
                ['name' => 'logo_dark', 'label' => 'Logo auf dunklem Grund (optional)', 'type' => 'media', 'width' => 'half'],
                ['name' => 'schema_type', 'label' => 'Art (für Suchmaschinen)', 'type' => 'select', 'default' => 'Organization', 'required' => true,
                    'options' => ['Organization' => 'Organisation', 'LocalBusiness' => 'Lokales Unternehmen', 'ProfessionalService' => 'Dienstleister / Agentur',
                        'MedicalBusiness' => 'Praxis / Gesundheit', 'Restaurant' => 'Gastronomie', 'Store' => 'Geschäft', 'EducationalOrganization' => 'Bildung',
                        'NGO' => 'Verein / gemeinnützig', 'GovernmentOrganization' => 'Behörde / Kommune', 'Person' => 'Person'],
                    'help' => 'Für strukturierte Daten (schema.org) – hilft Suchmaschinen, Name, Adresse und Zeiten richtig zuzuordnen.'],
                ['name' => 'default_meta_description', 'translate' => true, 'label' => 'Beschreibung für Suchmaschinen (Standard)', 'type' => 'textarea', 'rows' => 2, 'max' => 300,
                    'help' => 'Für Seiten ohne eigene Beschreibung (Seiteneinstellungen).'],
            ]],
            'contact' => ['label' => 'Kontakt & Zeiten', 'fields' => [
                ['name' => 'street', 'label' => 'Straße und Hausnummer', 'type' => 'text', 'max' => 120],
                ['name' => 'zip', 'label' => 'PLZ', 'type' => 'text', 'max' => 12, 'width' => 'half'],
                ['name' => 'city', 'label' => 'Ort', 'type' => 'text', 'max' => 80, 'width' => 'half'],
                ['name' => 'country', 'label' => 'Land (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
                ['name' => 'geo', 'label' => 'Standort für die Karte', 'type' => 'geo', 'width' => 'half',
                    'help' => '„Aus Adresse ermitteln“ sucht die Koordinaten (OpenStreetMap). Oder „Breite, Länge“ eintragen.'],
                ['name' => 'phone', 'label' => 'Telefon', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. 02841 123456'],
                ['name' => 'email', 'label' => 'E-Mail', 'type' => 'text', 'max' => 120, 'width' => 'half'],
                ['name' => 'hours', 'label' => 'Öffnungszeiten', 'type' => 'repeater', 'item_label' => 'Zeit', 'title_field' => 'tag', 'fields' => [
                    ['name' => 'tag', 'label' => 'Tag', 'type' => 'select', 'options' => $days, 'required' => true, 'width' => 'half'],
                    ['name' => 'notiz', 'label' => 'Hinweis (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'z. B. nach Vereinbarung'],
                    ['name' => 'von', 'label' => 'von', 'type' => 'time', 'width' => 'half'],
                    ['name' => 'bis', 'label' => 'bis', 'type' => 'time', 'width' => 'half'],
                    ['name' => 'pause_von', 'label' => 'Pause von (optional)', 'type' => 'time', 'width' => 'half'],
                    ['name' => 'pause_bis', 'label' => 'Pause bis', 'type' => 'time', 'width' => 'half'],
                ]],
                ['name' => 'hours_note', 'translate' => true, 'label' => 'Hinweis zu den Zeiten (optional)', 'type' => 'text', 'max' => 160, 'placeholder' => 'z. B. Termine nach Vereinbarung'],
                ['name' => 'social', 'label' => 'Social Media', 'type' => 'repeater', 'item_label' => 'Profil', 'title_field' => 'label', 'fields' => [
                    ['name' => 'label', 'label' => 'Name', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Instagram'],
                    ['name' => 'url', 'label' => 'Adresse', 'type' => 'url', 'width' => 'half'],
                ]],
            ]],
            'layout' => ['label' => 'Kopf, Fuß & Hinweis', 'fields' => [
                ['name' => 'header_cta_label', 'translate' => true, 'label' => 'Button im Kopf – Beschriftung (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => 'z. B. Termin vereinbaren'],
                ['name' => 'header_cta_link', 'label' => 'Button im Kopf – Ziel', 'type' => 'link', 'width' => 'half', 'help' => 'Seite, #anker, https://… – Sonderwerte „phone“ und „email“.'],
                ['name' => 'search_page', 'label' => 'Seite mit der Suche (optional)', 'type' => 'link', 'width' => 'half',
                    'help' => 'Seite mit dem Block „Suche“ – dann erscheint im Kopf eine Lupe.'],
                ['name' => 'topbar_text', 'translate' => true, 'label' => 'Infoleiste über dem Kopf (optional)', 'type' => 'text', 'max' => 120, 'help' => 'Kurzer Text neben Telefon und E-Mail – erscheint, wenn im Design eine Infoleiste gewählt ist.'],
                ['name' => 'footer_text', 'translate' => true, 'label' => 'Text im Fuß (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ['name' => 'footer_statement', 'translate' => true, 'label' => 'Großer Satz im Fuß (Design „Statement“)', 'type' => 'text', 'max' => 120, 'help' => '*Wort* hebt hervor.'],
                ['name' => 'notice_active', 'label' => 'Hinweisleiste zeigen', 'type' => 'bool', 'default' => false],
                ['name' => 'notice_text', 'translate' => true, 'label' => 'Hinweis', 'type' => 'text', 'max' => 200, 'placeholder' => 'z. B. Vom 22.12. bis 6.1. geschlossen.'],
            ]],
            ...$kitTab,
            'legal' => ['label' => 'Recht & Suchmaschinen', 'fields' => [
                ['name' => 'imprint_page', 'label' => 'Impressum', 'type' => 'link', 'width' => 'half'],
                ['name' => 'privacy_page', 'label' => 'Datenschutz', 'type' => 'link', 'width' => 'half'],
                ['name' => 'accessibility_page', 'label' => 'Erklärung zur Barrierefreiheit (optional)', 'type' => 'link', 'width' => 'half'],
                ['name' => 'robots', 'label' => 'robots.txt (optional)', 'type' => 'textarea', 'rows' => 4, 'max' => 2000,
                    'help' => 'Leer = Standard (alles erlaubt außer Backend und Bearbeiten-Ansichten). Die Zeile „Sitemap:“ kommt automatisch dazu.'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (null === self::$values) {
            $v = FluidAddon::instance()->getConfig(self::CONFIG, []);
            self::$values = is_array($v) ? $v : [];
        }

        return self::$values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // weitere Sprachen: Übersetzung, sonst Text der Startsprache
        $lang = \Redaxo\Core\Language\Language::getCurrentId();
        if ($lang !== \Redaxo\Core\Language\Language::getStartId()) {
            $t = self::translations($lang)[$key] ?? null;
            if (null !== $t && '' !== $t) {
                return $t;
            }
        }
        $all = self::all();

        return array_key_exists($key, $all) && '' !== $all[$key] && null !== $all[$key] ? $all[$key] : $default;
    }

    /** Übersetzte Werte einer weiteren Sprache (Felder mit 'translate' => true) */
    public static function translations(int $languageId): array
    {
        $all = FluidAddon::instance()->getConfig(self::CONFIG . '_i18n', []);

        return is_array($all[$languageId] ?? null) ? $all[$languageId] : [];
    }

    /** Felder eines Reiters, die je Sprache gepflegt werden */
    public static function translatable(string $tab): array
    {
        return array_values(array_filter(self::schema()[$tab]['fields'] ?? [], static fn (array $f) => !empty($f['translate'])));
    }

    /** Werte eines Reiters speichern (nur Felder des Schemas); weitere Sprache: nur die übersetzbaren Felder */
    public static function save(string $tab, array $input, ?int $languageId = null): void
    {
        $fields = self::schema()[$tab]['fields'] ?? null;
        if (null === $fields) {
            throw new ApiError('Unbekannter Bereich.');
        }
        if (null !== $languageId && $languageId !== \Redaxo\Core\Language\Language::getStartId()) {
            $i18n = FluidAddon::instance()->getConfig(self::CONFIG . '_i18n', []);
            $i18n = is_array($i18n) ? $i18n : [];
            foreach (self::translatable($tab) as $f) {
                $v = $input[$f['name']] ?? '';
                $i18n[$languageId][$f['name']] = is_array($v) ? '' : mb_substr(trim((string) $v), 0, (int) ($f['max'] ?? 2000));
            }
            FluidAddon::instance()->setConfig(self::CONFIG . '_i18n', $i18n);

            return;
        }
        $all = self::all();
        foreach ($fields as $f) {
            $v = $input[$f['name']] ?? ('bool' === $f['type'] ? false : '');
            $all[$f['name']] = match ($f['type']) {
                'bool' => (bool) $v,
                'repeater' => is_array($v) ? array_values(array_filter($v, 'is_array')) : [],
                default => is_array($v) ? '' : mb_substr(trim((string) $v), 0, (int) ($f['max'] ?? 2000)),
            };
            if (!empty($f['required']) && '' === $all[$f['name']]) {
                throw new ApiError('Bitte „' . $f['label'] . '“ ausfüllen.');
            }
            if ('kit' === $f['name'] && !isset(Kit::all()[$all['kit']])) {
                throw new ApiError('Unbekanntes Kit.');
            }
        }
        FluidAddon::instance()->setConfig(self::CONFIG, $all);
        self::$values = $all;
        Kit::reset();
    }

    /** Name der Website: Einstellung oder REDAXO-Instanzname */
    public static function name(bool $short = false): string
    {
        $name = trim((string) self::get('org_name', ''));
        $shortName = trim((string) self::get('short_name', ''));
        if ($short && '' !== $shortName) {
            return $shortName;
        }

        return '' !== $name ? $name : ('' !== $shortName ? $shortName : Core::getProject()->instanceName);
    }
}
