<?php
/*
 * Demo-Inhalte „REDAXO 6“ für das Kit fluid – `php bin/console fluid:demo --set=redaxo6 --replace`.
 * Stand: REDAXO 6 in Entwicklung (redaxo/core, Branch 6.x). Nur Aussagen, die sich im Code des Branches nachprüfen lassen.
 * Blöcke mit 'children' => [[Spalte, Block], …] werden als Layout mit Spalten-Blöcken (echte Slices) angelegt.
 */

$cta = [
    'type' => 'cta', 'tunes' => ['background' => 'accent'],
    'data' => [
        'variant' => 'band', 'eyebrow' => 'Ausprobieren',
        'title' => 'Diese Seite ist mit REDAXO 6 und fluid gebaut.',
        'text' => 'Anmelden, auf „Bearbeiten“ tippen – und jeden Text, jedes Bild und das ganze Design direkt hier ändern.',
        'button_label' => 'Wie Bearbeiten funktioniert', 'button_link' => '/bearbeiten',
        'button2_label' => 'Technik ansehen', 'button2_link' => '/technik',
    ],
];

return [
    'pages' => [
        // ------------------------------------------------------------------ Startseite
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'type', 'eyebrow' => 'REDAXO 6 · in Entwicklung',
                    'title' => 'Inhalte pflegen, *wo sie wirken*.',
                    'text' => 'REDAXO 6 räumt unter der Haube auf: PHP 8.5, Composer, Module und Templates als PHP-Klassen. Das macht etwas möglich, das in REDAXO lange gefehlt hat – Seiten direkt auf der Website bearbeiten, mit echten Slices.',
                    'button_label' => 'Was sich ändert', 'button_link' => '/was-sich-aendert',
                    'button2_label' => 'Bearbeiten auf der Website', 'button2_link' => '/bearbeiten',
                    'points' => "Seit 2004 · MIT-Lizenz\nModule als PHP-Klassen\nBearbeiten im Kontext",
                ]],
                ['type' => 'features', 'data' => [
                    'variant' => 'cards', 'eyebrow' => 'Unter der Haube', 'title' => 'Was REDAXO 6 mitbringt',
                    'intro' => 'Ein modernes Fundament – vertraut für alle, die REDAXO kennen, und ohne Altlasten.',
                    'size' => 'm',
                    'items' => [
                        ['icon' => 'package', 'title' => 'Composer von Anfang an', 'text' => 'Der Core ist das Paket redaxo/core, ein Projekt entsteht mit composer create-project. AddOns sind Composer-Pakete – installiert heißt aktiv.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'code', 'title' => 'Module und Templates als Klassen', 'text' => 'Mit #[AsModule] und #[AsTemplate] liegen sie als PHP-Dateien im Projekt – versioniert im Git statt als Code in der Datenbank.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'terminal-window', 'title' => 'console migrate', 'text' => 'Ein Befehl bringt die Datenbank auf den Stand des Codes: Tabellen werden beschrieben statt per SQL geändert, einmalige Schritte laufen als Migrationen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'tree-structure', 'title' => 'Struktur im Core', 'text' => 'Kategorien, Artikel, Slices, Arbeitsversion und Historie gehören zum Kern – kein AddOn „structure“ mehr.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'fingerprint', 'title' => 'Passkeys', 'text' => 'Anmelden am Backend ohne Passwort – mit Fingerabdruck, Gesichtserkennung oder Sicherheitsschlüssel (WebAuthn).', 'link_label' => '', 'link' => ''],
                        ['icon' => 'shield-check', 'title' => 'Betriebsmodi', 'text' => 'dev, live und hardened über .env: ausführliche Fehler in der Entwicklung, gehärtetes Backend auf dem Server.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'stats', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'row', 'eyebrow' => 'In Zahlen', 'title' => 'REDAXO auf einen Blick', 'intro' => '',
                    'items' => [
                        ['value' => '2004', 'label' => 'erste Version', 'text' => 'Content-Management seit über 20 Jahren'],
                        ['value' => '8.5', 'label' => 'PHP mindestens', 'text' => 'für REDAXO 6'],
                        ['value' => 'MIT', 'label' => 'Lizenz', 'text' => 'frei nutzbar, auch kommerziell'],
                        ['value' => '6.x', 'label' => 'Entwicklungszweig', 'text' => 'redaxo/core auf GitHub'],
                    ],
                ]],
                ['type' => 'layout', 'data' => ['preset' => '2-1', 'valign' => 'center', 'gap' => 'normal', 'stack' => 'default', 'reverse' => false],
                    'children' => [
                        [1, ['type' => 'richtext', 'data' => [
                            'variant' => 'standard', 'eyebrow' => 'Warum jetzt', 'title' => 'Zeit für ein neues Bearbeiten', 'intro' => '',
                            'text' => '<p>In REDAXO 5 war ein Modul freier PHP-Code: Eingabe und Ausgabe ganz in der Hand der Entwickler. Das ist flexibel – aber kein Editor weiß, welche Felder ein Modul hat.</p><p>In REDAXO 6 ist ein Modul eine <strong>Klasse</strong>. Damit kann es ein <strong>Feldschema</strong> mitbringen – und aus dem Schema entstehen Backend-Formular, Bearbeiten auf der Website und Live-Vorschau.</p>',
                            'measure' => 'normal', 'meta' => '', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                        ]]],
                        [2, ['type' => 'quote', 'tunes' => ['background' => 'tint'], 'data' => ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'items' => [
                            ['text' => 'Die Redaktion sieht, was sie ändert – und die Entwickler behalten Module, Templates und Daten im Griff.', 'name' => 'Leitgedanke', 'role' => 'fluid für REDAXO 6', 'image' => null],
                        ]]]],
                    ],
                ],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Technik
        [
            'slug' => 'technik', 'title' => 'Technik', 'menu' => true,
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'Technik',
                    'title' => 'Das Fundament von REDAXO 6', 'text' => 'Was im Branch 6.x von redaxo/core steckt – und wie ein Projekt entsteht.']],
                ['type' => 'steps', 'data' => [
                    'variant' => 'process', 'eyebrow' => 'Ablauf', 'title' => 'Vom leeren Ordner zur Website', 'intro' => '',
                    'marker' => 'auto', 'colors' => 'auto', 'motion' => 'auto',
                    'items' => [
                        ['meta' => 'Schritt 1', 'title' => 'composer create-project', 'text' => 'Das Projekt-Skelett redaxo/project holt den Core als Abhängigkeit.', 'icon' => ''],
                        ['meta' => 'Schritt 2', 'title' => '.env', 'text' => 'Datenbank (DATABASE_URL), Modus (dev, live, hardened) und Name der Installation.', 'icon' => ''],
                        ['meta' => 'Schritt 3', 'title' => 'console setup', 'text' => 'Einrichtung samt Admin-Konto – auch ohne Browser.', 'icon' => ''],
                        ['meta' => 'Schritt 4', 'title' => 'console migrate', 'text' => 'Bei jedem Update: Datenbank auf den Stand von Core, AddOns und Projekt bringen.', 'icon' => ''],
                    ],
                ]],
                ['type' => 'richtext', 'data' => [
                    'variant' => 'article', 'eyebrow' => '', 'title' => 'Die wichtigsten Bausteine', 'intro' => '', 'meta' => 'Stand: Branch 6.x',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => true, 'reveal' => 'off', 'scrollspy' => 'mark',
                    'text' => '<h3>Namespaces statt rex_-Klassen</h3><p>Alle Klassen des Cores liegen unter <code>Redaxo\Core\…</code> – etwa <code>Redaxo\Core\Content\Article</code> oder <code>Redaxo\Core\Database\Sql</code>. Die Datenbank-Klasse hat typisierte Getter wie <code>getIntValue()</code>.</p>'
                        . '<h3>Module und Templates als PHP-Klassen</h3><p>Ein Modul erbt von <code>Module</code> und trägt das Attribut <code>#[AsModule(\'key\', \'Name\')]</code>, mit den Methoden <code>input()</code> und <code>output()</code>. Templates erben von <code>Template</code>, rendern sich selbst und legen fest, welche Module in welchem Inhaltsbereich erlaubt sind. REDAXO findet die Klassen automatisch.</p>'
                        . '<h3>AddOns sind Composer-Pakete</h3><p>Ein Paket vom Typ <code>redaxo-addon</code> ist installiert und aktiv – ohne zusätzlichen Schalter. Seine Tabellen beschreibt es in <code>install()</code>; das läuft bei jedem <code>console migrate</code> und muss deshalb wiederholbar sein. Einmalige Schritte sind Migrationen mit eigenem Protokoll.</p>'
                        . '<h3>Extension Points</h3><p>Erweiterungen hängen sich weiter über Extension Points ein – teils als eigene Klassen, etwa <code>ArticleContentUpdated</code> für jede Änderung an Inhalten.</p>'
                        . '<h3>Symfony-Komponenten</h3><p>Konsole, Mailer, HTTP-Client, HTML-Sanitizer, Dotenv und Runtime kommen aus Symfony 8 – bewährte Bausteine statt Eigenbau.</p>',
                ]],
                ['type' => 'faq', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'stacked', 'eyebrow' => 'Fragen', 'title' => 'Häufige Fragen zu REDAXO 6', 'intro' => '',
                    'items' => [
                        ['q' => 'Ist REDAXO 6 schon fertig?', 'a' => '<p>Nein. REDAXO 6 entsteht im Branch 6.x von redaxo/core. Für laufende Projekte ist REDAXO 5 der gepflegte Zweig.</p>'],
                        ['q' => 'Welche PHP-Version brauche ich?', 'a' => '<p>REDAXO 6 setzt PHP 8.5 voraus.</p>'],
                        ['q' => 'Wo liegen Module und Templates?', 'a' => '<p>Als PHP-Klassen im Projekt (bzw. in AddOns) – nicht mehr als Code in der Datenbank. So lassen sie sich versionieren, prüfen und deployen wie jeder andere Code.</p>'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Was sich ändert
        [
            'slug' => 'was-sich-aendert', 'title' => 'Was sich ändert', 'menu' => true,
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'REDAXO 5 → 6',
                    'title' => 'Was sich gegenüber REDAXO 5 ändert', 'text' => 'Vertraute Ideen, neues Fundament: Struktur, Slices und Module bleiben – nur sauberer gebaut.']],
                ['type' => 'features', 'data' => [
                    'variant' => 'list', 'eyebrow' => '', 'title' => 'Bisher und jetzt', 'intro' => '', 'size' => 'l',
                    'items' => [
                        ['icon' => 'code', 'title' => 'Module: von Code in der Datenbank zu PHP-Klassen', 'text' => 'REDAXO 5: Ein- und Ausgabe als PHP im Backend gespeichert. REDAXO 6: Klasse mit #[AsModule], input() und output() – im Projekt versioniert.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'layout', 'title' => 'Templates: von Datenbank-Code zu Klassen mit Inhaltsbereichen', 'text' => 'Ein Template erbt von Template, rendert sich selbst und legt fest, welche Module in welchem Bereich erlaubt sind.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'package', 'title' => 'AddOns: vom Installer zu Composer', 'text' => 'REDAXO 5: herunterladen, installieren, aktivieren. REDAXO 6: Composer-Paket – im Projekt eingetragen heißt aktiv.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'tree-structure', 'title' => 'Struktur: vom AddOn zum Core', 'text' => 'Struktur, Inhalte, Arbeitsversion und Historie waren das AddOn „structure“ mit Plugins – jetzt sind sie Teil des Kerns.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'brackets-curly', 'title' => 'Klassen: von rex_article zu Redaxo\\Core\\Content\\Article', 'text' => 'Namespaces, Typen und Attribute statt globaler rex_-Klassen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'gear', 'title' => 'Konfiguration: .env und Betriebsmodi', 'text' => 'Datenbank und Modus kommen aus der Umgebung – dev, live oder hardened.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'quote', 'tunes' => ['background' => 'tint'], 'data' => ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'items' => [
                    ['text' => 'Slices bleiben Slices. Was sich ändert, ist, was man mit ihnen machen kann.', 'name' => 'fluid', 'role' => 'Blöcke für REDAXO 6', 'image' => null],
                ]]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Bearbeiten
        [
            'slug' => 'bearbeiten', 'title' => 'Bearbeiten', 'menu' => true,
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'fluid',
                    'title' => 'Bearbeiten direkt auf der Website', 'text' => 'Ein Feldschema je Block – daraus entstehen Backend-Formular, Bearbeiten im Kontext, Live-Vorschau und Ausgabe.']],
                ['type' => 'features', 'data' => [
                    'variant' => 'icons', 'eyebrow' => 'Im Alltag', 'title' => 'Was die Redaktion kann', 'intro' => '', 'size' => 'm',
                    'items' => [
                        ['icon' => 'pencil-simple', 'title' => 'Text direkt ändern', 'text' => 'Überschriften und Texte an Ort und Stelle – formatiert mit fett, kursiv, Marker, Farben, Listen und Links.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'sidebar-simple', 'title' => 'Alle Felder in der Seitenleiste', 'text' => 'Darstellung, Bilder aus dem Medienpool, Links über die Linkmap – mit Vorschau beim Tippen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'squares-four', 'title' => 'Blöcke in Spalten', 'text' => 'Layout wählen, Blöcke in die Spalten stellen – auf dem Telefon automatisch untereinander.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'palette', 'title' => 'Design mit Vorlagen', 'text' => 'Farben, Schriften, Formen, Kopf und Fuß – live auf der Seite, mit Prüfung des Kontrasts.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'eye', 'title' => 'Entwurf und Veröffentlichen', 'text' => 'Änderungen landen in der Arbeitsversion – „Veröffentlichen“ bringt sie auf die Website.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'toggle-left', 'title' => 'Online/Offline, verschieben, löschen', 'text' => 'Jeder Block hat seine Leiste – auch Blöcke in Spalten.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'richtext', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'standard', 'eyebrow' => 'Im Vergleich', 'title' => 'Was fluid anders macht als MForm, MBlock und blÖcks', 'intro' => '',
                    'measure' => 'wide', 'meta' => '', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p>Die bewährten AddOns lösen je eine Aufgabe im Backend: <strong>MForm</strong> baut Eingabeformulare für Module, <strong>MBlock</strong> macht Feldgruppen innerhalb eines Slices wiederholbar, <strong>blÖcks</strong> ergänzt die Slice-Liste um Verschieben, Kopieren und Online/Offline.</p>'
                        . '<p><strong>fluid</strong> setzt eine Ebene tiefer an – beim Modul selbst:</p>'
                        . '<ul><li><strong>Ein Schema für alles:</strong> Die Felder eines Blocks stehen einmal im Kit. Daraus entstehen das Formular im Backend, das Bearbeiten auf der Website, die Vorschau und die Daten der Ausgabe.</li>'
                        . '<li><strong>Echte Slices:</strong> Jeder Block ist ein Slice – auch die Blöcke in Layout-Spalten. Modulrechte, Arbeitsversion, Historie, Online/Offline und das Backend gelten wie immer.</li>'
                        . '<li><strong>Native Werte:</strong> Gespeichert wird über REX_INPUT_VALUE in value1 (Felder als JSON) und value2 (Abschnitt). Keine eigenen Tabellen, nichts neben REDAXO vorbei.</li>'
                        . '<li><strong>Wiederholgruppen eingebaut:</strong> Einträge hinzufügen, sortieren, entfernen – im Backend und in der Seitenleiste gleich.</li>'
                        . '<li><strong>Medienpool und Linkmap:</strong> Die Auswahl öffnet die echten REDAXO-Popups; Links auf Artikel bleiben als redaxo://ID gültig, auch wenn der Artikel umzieht.</li>'
                        . '<li><strong>Kit mit Design-System:</strong> Blöcke, Template und CSS gehören zusammen – mit Style-Editor und Vorlagen.</li></ul>',
                ]],
                ['type' => 'layout', 'data' => ['preset' => '1-1', 'valign' => 'stretch', 'gap' => 'normal', 'stack' => 'default', 'reverse' => false],
                    'children' => [
                        [1, ['type' => 'cta', 'data' => ['variant' => 'box', 'eyebrow' => 'Für Entwickler', 'title' => 'Ein Block = eine kleine Klasse',
                            'text' => 'Felder ins Kit, Ausgabe als View-Datei, fertig. Formular, Speichern und Bearbeiten auf der Website kommen von fluid.',
                            'button_label' => 'Technik', 'button_link' => '/technik', 'button2_label' => '', 'button2_link' => '']]],
                        [2, ['type' => 'cta', 'data' => ['variant' => 'box', 'eyebrow' => 'Für die Redaktion', 'title' => 'Sehen, was man ändert',
                            'text' => 'Kein Rätselraten mehr zwischen Formular und Ergebnis – bearbeitet wird dort, wo der Text steht.',
                            'button_label' => 'Zur Startseite', 'button_link' => '/start', 'button2_label' => '', 'button2_link' => '']]],
                    ],
                ],
            ],
        ],
    ],
];
