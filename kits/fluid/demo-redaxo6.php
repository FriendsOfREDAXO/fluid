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

// YForm (nur wenn installiert): Tabellen für die Seite „Daten“ – Beispieldaten, als solche gekennzeichnet
$yform = [
    'rex_yf_termin' => ['name' => 'Termine (Beispiel)', 'fields' => [
        ['type_id' => 'value', 'type_name' => 'text', 'name' => 'title', 'label' => 'Titel'],
        ['type_id' => 'value', 'type_name' => 'date', 'name' => 'date', 'label' => 'Datum', 'format' => 'd.m.Y'],
        ['type_id' => 'value', 'type_name' => 'text', 'name' => 'place', 'label' => 'Ort'],
        ['type_id' => 'value', 'type_name' => 'be_media', 'name' => 'image', 'label' => 'Bild', 'types' => 'jpg,jpeg,png,webp,avif'],
        ['type_id' => 'value', 'type_name' => 'textarea', 'name' => 'text', 'label' => 'Beschreibung'],
        ['type_id' => 'value', 'type_name' => 'checkbox', 'name' => 'online', 'label' => 'Online', 'default' => 1, 'output_values' => 'Nein,Ja'],
        ['type_id' => 'validate', 'type_name' => 'empty', 'name' => 'title', 'message' => 'Bitte einen Titel angeben.'],
    ], 'rows' => [
        ['title' => 'Online-Demo: Bearbeiten auf der Website', 'date' => '2026-11-12', 'place' => 'Online', 'image' => 'testbild-verlauf.jpg', 'text' => "Eine Stunde fluid live: Texte direkt auf der Seite ändern, Blöcke einfügen, Design anpassen und veröffentlichen.\n\nBeispieltermin – zeigt, wie eine YForm-Tabelle als Datenliste erscheint.", 'online' => 1],
        ['title' => 'Workshop: Eigene Blöcke bauen', 'date' => '2026-11-26', 'place' => 'Online', 'image' => '', 'text' => 'Ein Block ist eine Modulklasse, ein Feldschema und eine View. Wir bauen gemeinsam einen Block mit Bild, Text und Wiederholgruppe.', 'online' => 1],
        ['title' => 'Sprechstunde: Von REDAXO 5 nach 6', 'date' => '2026-12-03', 'place' => 'Online', 'image' => '', 'text' => 'Fragen zu Composer, Modulen als Klassen und console migrate – mitbringen, was im eigenen Projekt ansteht.', 'online' => 1],
        ['title' => 'Entwurf: noch nicht veröffentlicht', 'date' => '2026-12-17', 'place' => 'Online', 'image' => '', 'text' => 'Dieser Eintrag ist offline und erscheint nicht in der Liste.', 'online' => 0],
    ]],
    'rex_yf_anfrage' => ['name' => 'Anfragen (Beispiel)', 'fields' => [
        ['type_id' => 'value', 'type_name' => 'text', 'name' => 'name', 'label' => 'Name', 'attributes' => '{"autocomplete":"name"}'],
        ['type_id' => 'value', 'type_name' => 'email', 'name' => 'email', 'label' => 'E-Mail', 'attributes' => '{"autocomplete":"email"}'],
        ['type_id' => 'value', 'type_name' => 'choice', 'name' => 'topic', 'label' => 'Worum geht es?', 'choices' => '{"Frage zu fluid":"frage","Feedback":"feedback","Mitmachen":"mitmachen"}'],
        ['type_id' => 'value', 'type_name' => 'textarea', 'name' => 'message', 'label' => 'Nachricht'],
        ['type_id' => 'value', 'type_name' => 'checkbox', 'name' => 'privacy', 'label' => 'Ich bin einverstanden, dass meine Angaben zur Beantwortung gespeichert werden.', 'output_values' => 'Nein,Ja'],
        ['type_id' => 'value', 'type_name' => 'datestamp', 'name' => 'created', 'label' => 'Eingegangen', 'format' => 'Y-m-d H:i:s', 'only_empty' => 1],
        ['type_id' => 'validate', 'type_name' => 'empty', 'name' => 'name', 'message' => 'Bitte Ihren Namen angeben.'],
        ['type_id' => 'validate', 'type_name' => 'type', 'name' => 'email', 'type' => 'email', 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.'],
        ['type_id' => 'validate', 'type_name' => 'empty', 'name' => 'message', 'message' => 'Bitte eine Nachricht schreiben.'],
        ['type_id' => 'validate', 'type_name' => 'compare_value', 'name' => 'privacy', 'compare_value' => '1', 'compare_type' => '!=', 'message' => 'Bitte der Speicherung zustimmen.'],
    ], 'rows' => []],
];

return [
    'yform' => $yform,
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

        // ------------------------------------------------------------------ Suche (nicht im Menü – Lupe im Kopf)
        [
            'slug' => 'suche', 'title' => 'Suche', 'menu' => false,
            'blocks' => [
                ['type' => 'search', 'data' => ['eyebrow' => '', 'title' => 'Suche', 'intro' => 'Durchsucht alle veröffentlichten Seiten – auch mit Tippfehlern.', 'placeholder' => '', 'limit' => 20, 'empty_text' => '']],
            ],
        ],
        // ------------------------------------------------------------------ Daten (YForm)
        [
            'slug' => 'daten', 'title' => 'Daten', 'menu' => true, 'requires' => 'yform',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'fluid + YForm',
                    'title' => 'Daten und Formulare aus *YForm*', 'text' => 'Zwei Blöcke verbinden fluid mit den Tabellen von YForm: eine Datenliste mit Detailansicht und ein Formular, das Einträge speichert. Felder und Prüfungen kommen aus dem Table Manager.']],
                ['type' => 'datalist', 'data' => ['variant' => 'cards', 'eyebrow' => 'Datenliste', 'title' => 'Termine', 'intro' => 'Beispieldaten aus der YForm-Tabelle rex_yf_termin – nur Einträge mit „Online“, nach Datum sortiert.',
                    'table' => 'rex_yf_termin', 'title_field' => 'rex_yf_termin.title', 'image_field' => 'rex_yf_termin.image', 'text_field' => 'rex_yf_termin.text', 'meta_field' => 'rex_yf_termin.date',
                    'filter_field' => 'rex_yf_termin.online', 'filter_value' => '1', 'sort_field' => 'rex_yf_termin.date', 'sort_dir' => 'asc', 'limit' => 0, 'size' => 'm', 'detail' => true, 'empty_text' => '']],
                ['type' => 'form', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'split', 'eyebrow' => 'Formular', 'title' => 'Schreiben Sie uns',
                    'intro' => 'Das Formular kommt aus der YForm-Tabelle rex_yf_anfrage: Pflichtfelder, E-Mail-Prüfung und Einwilligung sind dort festgelegt. Einträge landen im Backend unter YForm.',
                    'table' => 'rex_yf_anfrage', 'submit_label' => 'Nachricht senden', 'notify' => '', 'success' => 'Vielen Dank! Ihre Nachricht ist angekommen – in dieser Demo landet sie nur in der YForm-Tabelle.',
                    'note' => 'Ihre Angaben verwenden wir nur zur Beantwortung.']],
            ],
        ],
        // ------------------------------------------------------------------ Bausteine (Blöcke des Kits im Einsatz)
        [
            'slug' => 'bausteine', 'title' => 'Bausteine', 'menu' => true,
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'Kit fluid',
                    'title' => 'Bausteine für jede Seite', 'text' => 'Jeder Abschnitt ist ein Block mit Feldschema – und ein echter REDAXO-Slice.']],
                ['type' => 'logos', 'data' => ['variant' => 'marquee', 'eyebrow' => '', 'title' => 'Gebaut auf bewährten Bausteinen', 'items' => [
                    ['name' => 'REDAXO', 'link' => '', 'image' => null], ['name' => 'PHP 8.5', 'link' => '', 'image' => null],
                    ['name' => 'Composer', 'link' => '', 'image' => null], ['name' => 'Symfony', 'link' => '', 'image' => null],
                    ['name' => 'Intervention Image', 'link' => '', 'image' => null], ['name' => 'Friends Of REDAXO', 'link' => '', 'image' => null],
                ]]],
                ['type' => 'bento', 'data' => ['eyebrow' => 'Auf einen Blick', 'title' => 'Was fluid mitbringt', 'intro' => '', 'items' => [
                    ['size' => 'big', 'tone' => 'dark', 'icon' => 'sparkle', 'image' => null, 'eyebrow' => 'Bearbeiten im Kontext', 'title' => 'Texte, Bilder und Design direkt auf der Seite ändern.', 'text' => 'Mit Live-Vorschau, Arbeitsversion und Veröffentlichen.', 'link' => '/bearbeiten'],
                    ['size' => 's', 'tone' => 'highlight', 'icon' => '', 'image' => null, 'eyebrow' => '18', 'title' => 'Design-Vorlagen', 'text' => 'Von der Kanzlei bis zum Startup.', 'link' => ''],
                    ['size' => 's', 'tone' => 'card', 'icon' => 'squares-four', 'image' => null, 'eyebrow' => '', 'title' => 'Layout mit Spalten', 'text' => 'Spalten-Blöcke sind echte Slices.', 'link' => ''],
                    ['size' => 'wide', 'tone' => 'accent', 'icon' => 'palette', 'image' => null, 'eyebrow' => '', 'title' => 'Style-Editor live auf der Seite', 'text' => 'Farben, Schriften, Formen – mit Kontrastprüfung.', 'link' => ''],
                    ['size' => 's', 'tone' => 'tint', 'icon' => 'pencil-simple', 'image' => null, 'eyebrow' => '', 'title' => 'Bilder bearbeiten', 'text' => 'Zuschneiden, Drehen, Entzerren – das Original bleibt.', 'link' => ''],
                    ['size' => 's', 'tone' => 'card', 'icon' => 'package', 'image' => null, 'eyebrow' => '', 'title' => 'Composer-Pakete', 'text' => 'fluid und image_editor als AddOns.', 'link' => ''],
                ]]],
                ['type' => 'tabs', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'top', 'eyebrow' => 'Für wen', 'title' => 'Ein System, drei Blickwinkel', 'intro' => '', 'items' => [
                    ['label' => 'Redaktion', 'text' => '<p>Sehen, was man ändert: Text direkt auf der Seite, alle Felder in der Seitenleiste, Bilder aus dem Medienpool, Links über die Linkmap. Änderungen landen im Entwurf – erst „Veröffentlichen“ bringt sie live.</p>', 'image' => null],
                    ['label' => 'Entwicklung', 'text' => '<p>Ein Block ist eine kleine Klasse: Felder ins Kit, Ausgabe als View-Datei. Formular, Speichern und Bearbeiten auf der Website kommen von fluid. Daten liegen nativ in value1/value2 – keine eigenen Tabellen.</p>', 'image' => null],
                    ['label' => 'Agentur', 'text' => '<p>Kits mit Design-System und Vorlagen: ein Projekt schnell auf das Erscheinungsbild der Kundschaft bringen – und die Redaktion pflegt danach selbst.</p>', 'image' => null],
                ]]],
                ['type' => 'pricing', 'data' => ['variant' => 'cards', 'eyebrow' => 'Mitmachen', 'title' => 'Drei Wege zu fluid', 'intro' => 'Open Source unter MIT-Lizenz – Beispielwege, keine Preisliste.', 'items' => [
                    ['name' => 'Ausprobieren', 'badge' => '', 'price' => '0 €', 'period' => 'MIT-Lizenz', 'text' => 'Lokal mit REDAXO 6 installieren und die Demo durchklicken.', 'features' => "REDAXO 6 (Branch 6.x)\nfluid:demo legt alles an\nKeine Anmeldung nötig", 'highlight' => false, 'button_label' => 'Technik', 'button_link' => '/technik'],
                    ['name' => 'Mitmachen', 'badge' => 'Empfohlen', 'price' => '0 €', 'period' => 'Friends Of REDAXO', 'text' => 'Ideen, Fehlerberichte und Blöcke beisteuern.', 'features' => "Issues und Pull Requests\nEigene Kits und Blöcke\nÜbersetzungen", 'highlight' => true, 'button_label' => 'Was sich ändert', 'button_link' => '/was-sich-aendert'],
                    ['name' => 'Einsetzen', 'badge' => '', 'price' => '0 €', 'period' => 'sobald stabil', 'text' => 'In Projekten nutzen, wenn REDAXO 6 erscheint.', 'features' => "Composer-Paket\nArbeitsversion und Historie\nRechte wie gewohnt", 'highlight' => false, 'button_label' => 'Bearbeiten', 'button_link' => '/bearbeiten'],
                ], 'rows' => [], 'note' => '']],
            ],
        ],
    ],
];
