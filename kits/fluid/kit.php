<?php
/*
 * Kit „fluid“ für friendsofredaxo/fluid – übernommen aus KLXM Studio (MIT): breakpointlos, Container-Queries,
 * fließende Schrift- und Abstandsstufen. Felder im Format der Studio-Kits (siehe FieldForm).
 */

$head = fn(bool $required = false, bool $intro = true) => array_merge([
    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
    ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'max' => 110, 'width' => 'half', 'required' => $required],
], $intro ? [['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400]] : []);

$buttons = [
    ['name' => 'button_label', 'label' => 'Button 1 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button_link', 'label' => 'Button 1 – Link', 'type' => 'link', 'width' => 'half',
        'help' => 'Seite, #anker, https://… – Sonderwerte „phone“ und „email“ nutzen „Website“.'],
    ['name' => 'button2_label', 'label' => 'Button 2 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button2_link', 'label' => 'Button 2 – Link', 'type' => 'link', 'width' => 'half'],
];

$ratios = ['16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4'];
// Breakpointlos: statt „Spalten“ eine Mindestbreite je Eintrag – das Raster füllt die Zeile selbst (auto-fit, minmax)
$size = ['name' => 'size', 'label' => 'Breite je Eintrag (mindestens)', 'type' => 'select', 'required' => true, 'default' => 'm', 'width' => 'half',
    'options' => ['s' => 'Schmal (viele nebeneinander)', 'm' => 'Mittel', 'l' => 'Breit (wenige nebeneinander)'],
    'help' => 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz – auf jedem Gerät.'];
$link = fn(string $prefix = 'link', string $label = 'Link') => [
    ['name' => $prefix . '_label', 'label' => $label . ' – Beschriftung (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => $prefix, 'label' => $label, 'type' => 'link', 'width' => 'half'],
];

return [
    'label' => 'Fluid (breakpointlos)',
    // Style-Editor: Farben, Schriften, Form, Kopf & Fuß, Vorlagen (FriendsOfRedaxo\Fluid\Design)
    'design' => require __DIR__ . '/design.php',
    // Design-Optionen mit eigenem Stylesheet (nur das Gewählte wird geladen): css/opt-{option}-{wert}.css
    'design_css' => ['header', 'footer', 'pagebg', 'buttons', 'eyebrow', 'cards', 'dividers', 'palette', 'sections'],
    'backgrounds' => ['white' => 'Standard', 'muted' => 'Getönt', 'tint' => 'Akzent hell', 'accent' => 'Akzentfarbe', 'secondary' => 'Zweite Markenfarbe', 'dark' => 'Dunkel'],
    'dark_backgrounds' => ['accent', 'secondary', 'dark'],

    // Stylesheets und Skripte je Blocktyp (bzw. Typ:Variante) – nur auf Seiten, die sie brauchen
    'assets' => [
        'css/hero-x.css' => ['hero:fullbleed', 'hero:cards', 'hero:type'],
        'css/hx-scale.css' => ['hero:scale'], 'css/hx-collage.css' => ['hero:collage'], 'css/hx-compare.css' => ['hero:compare'],   // neue Einstiege
        'css/prose.css' => ['richtext', 'tabs', 'faq', 'media_text', '@rich'],
        'css/rich-extra.css' => ['richtext', 'faq', 'media_text'],   // Absatz-Stile der Formatierungsleiste (Tipp, Achtung, Dachzeile, Zentriert)   // @rich: Rich-Text-Stile in anderen Blöcken (Core\Sanitizer::styled)
        'css/layout.css' => ['layout'],
        'css/b-article.css' => ['richtext:article', 'richtext:columns'],
        'css/b-features.css' => ['features'],
        'css/b-media-text.css' => ['media_text'],
        'css/b-cta.css' => ['cta'],
        'css/reel.css' => ['cards:reel', 'quote:reel'],
        'css/b-bento.css' => ['bento'],
        'css/b-cards.css' => ['cards'],
        'css/b-logos.css' => ['logos'],
        'css/b-stats.css' => ['stats'],
        'css/b-quote.css' => ['quote'],
        'css/b-steps.css' => ['steps'],
        'css/b-pricing.css' => ['pricing'],
        'css/b-faq.css' => ['faq'],
        'css/b-tabs.css' => ['tabs'],
        'css/b-scrolly.css' => ['scrolly'],
        'css/b-contact.css' => ['contact', 'map'],
        'css/pages.css' => ['contact'],
        'css/b-search.css' => ['search'],
        'css/b-video.css' => ['video'],
        'js/embed.js' => ['video'],
        'css/b-downloads.css' => ['downloads'],
        'css/dataform.css' => ['contact', 'form'],
        'css/b-form.css' => ['form', 'contact'],
        'css/data.css' => ['datalist'],
        'js/tabs.js' => ['tabs'],
        'js/reel.js' => ['quote', 'cards'],
        'js/scrolly.js' => ['scrolly'],
    ],

    'blocks' => [
        'hero' => [
            'label' => 'Einstieg (Hero)', 'icon' => 'star', 'group' => 'Kopf',
            'help' => 'Große Überschrift der Seite (H1). Pro Seite genau einmal verwenden – „Seitenkopf“ für Unterseiten. Alle Varianten ordnen sich nach verfügbarem Platz an.',
            'variants' => [
                'split' => 'Text und Bild nebeneinander',
                'centered' => 'Zentriert, Bild darunter',
                'fullbleed' => 'Vollflächiges Bild oder Video mit Abdunkelung',
                'cards' => 'Mit gestapelten Karten',
                'type' => 'Großer Schriftzug (typografisch)',
                'compact' => 'Seitenkopf (ohne Bild)',
                'scale' => 'Fließende Skala (Überschrift + Seitenspalte)',
            ],
            // Wann welche Variante? (Handbuch → Alle Blöcke → Einstieg)
            'variant_help' => [
                'split' => 'Startseite mit einem aussagekräftigen Bild – der Klassiker.',
                'centered' => 'Kurze, starke Botschaft; Bild im Breitbild darunter.',
                'fullbleed' => 'Stimmung zählt: Raum, Landschaft, Werkstatt – großes Bild oder kurzes stummes Video hinter dem Text.',
                'cards' => 'Drei Stärken sollen sofort ins Auge fallen, z. B. Leistungen oder Versprechen.',
                'type' => 'Ohne gutes Foto oder für eine klare Haltung: die Überschrift ist das Bild.',
                'compact' => 'Unterseiten: Seitentitel mit kurzer Einleitung.',
                'scale' => 'Typografischer Auftritt mit Fakten: große Überschrift, daneben Text und Buttons, darunter bis zu vier Kennzahlen oder Schlagworte. Passt sich dem eigenen Platz an – funktioniert auch in schmalen Spalten.',
            ],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 110,
                    'help' => 'Ein Wort in *Sternchen* wird hervorgehoben (Akzentfarbe bzw. kursiv bei Serifen).'],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'points', 'label' => 'Kurze Pluspunkte (eine pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3,
                    'help' => 'Erscheinen als Häkchen unter den Buttons, z. B. „Termine auch samstags“.'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1600 px breit. Alt-Text in der Mediathek pflegen.'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios,
                    'help' => 'Bei „Zentriert“ immer 16:9; bei „Vollflächig“ füllt das Bild den Abschnitt.'],
                ['name' => 'video', 'label' => 'Hintergrundvideo (MP4, stumm, nur „Vollflächig“)', 'type' => 'file', 'width' => 'half', 'variants' => ['fullbleed'],
                    'help' => 'Kurz und klein (< 8 MB). Startet nur ohne „Bewegung reduzieren“ und hat eine Pause-Schaltfläche. Das Bild dient als Standbild.'],
                ['name' => 'overlay', 'label' => 'Abdunkelung (nur „Vollflächig“)', 'type' => 'select', 'required' => true, 'default' => 'strong', 'width' => 'half', 'variants' => ['fullbleed'],
                    'options' => ['strong' => 'Stark (empfohlen)', 'medium' => 'Mittel', 'gradient' => 'Verlauf von unten']],
                ['name' => 'cards', 'label' => 'Karten (nur „Mit gestapelten Karten“)', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 3, 'variants' => ['cards'], 'fields' => [
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 50, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'text', 'max' => 120],
                ]],
                // Neue Varianten: Felder erscheinen nur dort (Core\Fields 'variants')
                ['name' => 'scale_items', 'label' => 'Stufenleiste: Kennzahlen oder Schlagworte (eine pro Zeile, bis zu 4)', 'type' => 'textarea', 'rows' => 4, 'variants' => ['scale'],
                    'help' => '„Wert: Bezeichnung“ ergibt eine Kennzahl, z. B. „24 h: Rückmeldung“; eine Zeile ohne Doppelpunkt ein Schlagwort. Jede Stufe ist eine Schriftstufe kleiner – wie die Typo-Skala. Nur belegbare Zahlen.'],
            ],
        ],
        'richtext' => [
            'nestable' => ['standard', 'columns'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Fließtext / Artikel', 'icon' => 'paragraph', 'group' => 'Inhalt',
            'help' => 'Freier Text mit Zwischenüberschriften, Listen, Zitaten und Links. „Artikel“: angenehme Lesebreite mit automatischem Inhaltsverzeichnis.',
            'variants' => ['standard' => 'Standard', 'article' => 'Artikel mit Inhaltsverzeichnis', 'columns' => 'Mehrspaltig (Zeitungssatz)'],
            // Im Editor: Griff am Rand des Textes ändert „Textbreite“ (Klasse je Wert, Core\Theme::editorConfig → drags)
            'drags' => [['field' => 'measure', 'target' => '.prose:not(.prose--columns)', 'class' => 'prose--m-{v}', 'label' => 'Textbreite']],
            'fields' => [
                ...$head(false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'measure', 'label' => 'Textbreite', 'type' => 'select', 'required' => true, 'default' => 'normal', 'width' => 'half', 'variants' => ['standard', 'article'],
                    'options' => ['narrow' => 'Schmal', 'normal' => 'Lesebreite', 'wide' => 'Breit', 'full' => 'Volle Breite'],
                    'help' => 'Auch auf der Seite einstellbar: am rechten Rand des Textes ziehen.'],
                ['name' => 'meta', 'label' => 'Angaben über dem Text (optional, bei „Artikel“)', 'type' => 'text', 'max' => 120, 'placeholder' => 'z. B. Redaktion · 12. März 2026'],
                ['name' => 'dropcap', 'label' => 'Initiale (großer erster Buchstabe)', 'type' => 'bool', 'default' => false, 'width' => 'half'],
                ['name' => 'toc', 'label' => 'Inhaltsverzeichnis anzeigen (bei „Artikel“)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'reveal', 'label' => 'Einblenden, wenn der Text in den Blick kommt', 'type' => 'select', 'required' => true, 'default' => 'off', 'width' => 'half',
                    'options' => ['off' => 'Aus', 'block' => 'Ganzer Block', 'paragraphs' => 'Absätze nacheinander'],
                    'help' => 'Art der Bewegung: Design → Bewegung & Farbschema („Art des Einblendens“). Nur mit eingeschalteten Animationen; Besucher mit „Bewegung reduzieren“ sehen den Text sofort.'],
                ['name' => 'scrollspy', 'label' => 'Beim Scrollen mitlaufen (Inhaltsverzeichnis)', 'type' => 'select', 'required' => true, 'default' => 'off', 'width' => 'half', 'variants' => ['article'],
                    'options' => ['off' => 'Aus', 'mark' => 'Aktuellen Abschnitt markieren', 'progress' => 'Markieren mit Lesefortschritt'],
                    'help' => 'Die Markierung gleitet zum Abschnitt, der gerade gelesen wird. Ohne Bewegung (Design oder „Bewegung reduzieren“) springt sie ohne Animation.'],
            ],
        ],
        'media_text' => [
            'label' => 'Text + Bild', 'icon' => 'square-half', 'group' => 'Inhalt',
            'help' => '„Abwechselnd“: mehrere Blöcke nacheinander wechseln automatisch die Seite. „Geteilt“: Bild und Text je halb, randlos und auf Wunsch bildschirmhoch.',
            'variants' => ['auto' => 'Abwechselnd (automatisch)', 'right' => 'Bild rechts', 'left' => 'Bild links', 'split' => 'Geteilt, randlos (Split-Screen)', 'overlap' => 'Text-Karte über großem Bild'],
            'fields' => [
                ...$head(true, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'list', 'label' => 'Aufzählung mit Häkchen (ein Punkt pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1200 px breit.'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
                ['name' => 'split', 'label' => 'Aufteilung Bild/Text', 'type' => 'select', 'required' => true, 'default' => 'even', 'width' => 'half', 'variants' => ['auto', 'right', 'left'],
                    'options' => ['text' => 'Text breiter', 'even' => 'Ausgewogen', 'media' => 'Bild breiter', 'mediaxl' => 'Bild deutlich breiter'],
                    'help' => 'Auch auf der Seite einstellbar: am inneren Rand des Bildes ziehen.'],
            ],
            'drags' => [['field' => 'split', 'target' => '.mt__media', 'class' => 'mt--s-{v}', 'apply' => '.mt', 'label' => 'Aufteilung']],
        ],
        'features' => [
            'nestable' => true,   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Merkmale / Leistungen', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Leistungen, Vorteile oder Angebote mit Symbol oder Bild. Anzahl nebeneinander ergibt sich aus dem Platz.',
            'variants' => ['cards' => 'Karten', 'icons' => 'Große Symbole, ohne Rahmen', 'list' => 'Liste mit Symbol links', 'numbered' => 'Nummeriert'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'items', 'label' => 'Einträge', 'type' => 'repeater', 'item_label' => 'Eintrag', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'oder Bild (3:2)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 70],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 320],
                    ...$link(),
                ]],
            ],
        ],
        'cards' => [
            'nestable' => ['image', 'horizontal', 'overlay'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Karten', 'icon' => 'copy', 'group' => 'Inhalt',
            'help' => 'Teaser mit Bild – klassisch, Text auf dem Bild, quer oder als wischbares Band (Reel). Karten stellen sich auf ihre eigene Breite ein.',
            'variants' => ['image' => 'Bild oben', 'overlay' => 'Text auf dem Bild', 'horizontal' => 'Quer (Bild links)', 'reel' => 'Wischbares Band (Reel)'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '3:2', 'width' => 'half', 'options' => $ratios],
                ['name' => 'items', 'label' => 'Karten', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'image', 'label' => 'Bild', 'type' => 'media'],
                    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 90, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                    ...$link(),
                ]],
                ['name' => 'more_label', 'label' => 'Button unter den Karten (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'more_link', 'label' => 'Button-Link', 'type' => 'link', 'width' => 'half'],
            ],
        ],
        'stats' => [
            'nestable' => ['row', 'cards'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Kennzahlen', 'icon' => 'chart-bar', 'group' => 'Inhalt',
            'help' => 'Nur belegbare Zahlen verwenden.',
            'variants' => ['row' => 'Große Zahlen in einer Reihe', 'cards' => 'Karten', 'split' => 'Überschrift links, Zahlen rechts'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kennzahlen', 'type' => 'repeater', 'item_label' => 'Kennzahl', 'title_field' => 'value', 'max_items' => 6, 'fields' => [
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 25+'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 50, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Erläuterung (optional)', 'type' => 'text', 'max' => 140],
                ]],
            ],
        ],
        'quote' => [
            'nestable' => ['single', 'grid'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Zitat / Stimmen', 'icon' => 'quotes', 'group' => 'Inhalt',
            'help' => '„Groß“: ein Zitat als Blickfang (Pull-Quote). „Raster“, „Band“ und „Laufband“ für mehrere Stimmen (Laufband hält bei Maus oder Fokus an). Nur echte, freigegebene Stimmen verwenden.',
            'variants' => ['single' => 'Groß (ein Zitat)', 'grid' => 'Raster (Mauerwerk)', 'reel' => 'Wischbares Band', 'marquee' => 'Laufband (läuft langsam durch)'],
            'fields' => [
                ...$head(false, false),
                ['name' => 'items', 'label' => 'Zitate', 'type' => 'repeater', 'item_label' => 'Zitat', 'title_field' => 'name', 'max_items' => 12, 'fields' => [
                    ['name' => 'text', 'label' => 'Zitat', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 480],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'role', 'label' => 'Funktion / Organisation', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Porträt (optional, 1:1)', 'type' => 'media'],
                ]],
            ],
        ],
        'steps' => [
            'nestable' => ['numbers', 'timeline'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Ablauf / Zeitleiste', 'icon' => 'path', 'group' => 'Inhalt',
            'help' => 'Nummerierte Schritte, eine Zeitleiste mit Datum/Phase oder ein Prozess mit Verbindungslinien.',
            'variants' => ['numbers' => 'Nummerierte Schritte', 'timeline' => 'Zeitleiste', 'process' => 'Prozess mit Pfeilen'],
            'fields' => [
                ...$head(),
                // Darstellung der Kreise (Zeitleiste, Prozess); „Wie Design“ folgt der Farbwirkung unter Design (z. B. „Farbenfroh“)
                ['name' => 'marker', 'label' => 'Kreise', 'type' => 'select', 'required' => true, 'default' => 'auto', 'width' => 'half', 'variants' => ['timeline', 'process'],
                    'options' => ['auto' => 'Wie Design', 'filled' => 'Gefüllt', 'soft' => 'Zart gefüllt mit Rand', 'ring' => 'Nur Rand', 'dot' => 'Kleiner Punkt (ohne Nummer)']],
                ['name' => 'colors', 'label' => 'Farben', 'type' => 'select', 'required' => true, 'default' => 'auto', 'width' => 'half', 'variants' => ['timeline', 'process', 'numbers'],
                    'options' => ['auto' => 'Wie Design', 'alternate' => 'Farbig abwechselnd', 'accent' => 'Eine Farbe (Akzent)', 'neutral' => 'Neutral (Schriftfarbe)']],
                ['name' => 'motion', 'label' => 'Animation', 'type' => 'select', 'required' => true, 'default' => 'auto', 'width' => 'half',
                    'options' => ['auto' => 'Wie Website (einblenden)', 'sequence' => 'Nacheinander einblenden', 'draw' => 'Linie wächst beim Scrollen (Zeitleiste)', 'none' => 'Keine Animation'],
                    'help' => 'Animationen entfallen, wenn Besucher in ihrem System „Bewegung reduzieren“ eingestellt haben. „Nacheinander einblenden“ braucht die Bewegung der Website (Design).'],
                ['name' => 'items', 'label' => 'Schritte', 'type' => 'repeater', 'item_label' => 'Schritt', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'meta', 'label' => 'Phase / Datum (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Woche 1'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                    ['name' => 'icon', 'label' => 'Symbol statt Nummer (optional)', 'type' => 'icon'],
                ]],
            ],
        ],
        'faq' => [
            'nestable' => ['stacked'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Fragen & Antworten', 'icon' => 'question', 'group' => 'Inhalt',
            'help' => 'Aufklappbare Fragen (ohne JavaScript). Suchmaschinen erhalten die Fragen als strukturierte Daten (FAQPage).',
            'variants' => ['split' => 'Überschrift links, Fragen rechts', 'stacked' => 'Untereinander, zentriert'],
            'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],
            'fields' => [
                ...$head(true),
                ['name' => 'items', 'label' => 'Fragen', 'type' => 'repeater', 'item_label' => 'Frage', 'title_field' => 'q', 'fields' => [
                    ['name' => 'q', 'label' => 'Frage', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['name' => 'a', 'label' => 'Antwort', 'type' => 'richtext', 'required' => true],
                ]],
            ],
        ],
        'cta' => [
            'nestable' => ['box', 'big'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Handlungsaufruf', 'icon' => 'megaphone', 'group' => 'Inhalt', 'background' => 'accent',
            'variants' => ['band' => 'Band über die volle Breite', 'box' => 'Hervorgehobene Box', 'split' => 'Mit Bild', 'big' => 'Großer Schriftzug'],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild (bei „Mit Bild“)', 'type' => 'media'],
            ],
        ],
        'logos' => [
            'nestable' => ['grid'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Logos (Partner, Kunden)', 'icon' => 'handshake', 'group' => 'Medien',
            'help' => 'Als ruhiges Raster oder Laufband (ohne JavaScript; steht bei „Bewegung reduzieren“ still und hält bei Maus/Fokus an). Ohne Bild erscheint der Name als Schriftzug. Nur Logos mit Freigabe verwenden.',
            'variants' => ['grid' => 'Raster', 'marquee' => 'Laufband'],
            'fields' => [
                ...$head(false, false),
                ['name' => 'items', 'label' => 'Logos', 'type' => 'repeater', 'item_label' => 'Logo', 'title_field' => 'name', 'max_items' => 16, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link (optional)', 'type' => 'link', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Logo (SVG oder PNG, transparent)', 'type' => 'media'],
                ]],
            ],
        ],
        'bento' => [
            'nestable' => false,   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Bento-Raster', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Kacheln unterschiedlicher Größe in einem dichten Raster – für Leistungen, Zahlen und Bilder. Kacheln rücken bei wenig Platz automatisch untereinander.',
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kacheln', 'type' => 'repeater', 'item_label' => 'Kachel', 'title_field' => 'title', 'max_items' => 10, 'fields' => [
                    ['name' => 'size', 'label' => 'Größe', 'type' => 'select', 'required' => true, 'default' => 's', 'width' => 'half',
                        'options' => ['s' => 'Normal', 'wide' => 'Breit', 'tall' => 'Hoch', 'big' => 'Groß (breit + hoch)']],
                    ['name' => 'tone', 'label' => 'Fläche', 'type' => 'select', 'required' => true, 'default' => 'card', 'width' => 'half',
                        'options' => ['card' => 'Karte', 'tint' => 'Akzent hell', 'highlight' => 'Zweitfarbe', 'accent' => 'Akzentfarbe', 'dark' => 'Dunkel', 'image' => 'Bild mit Text darauf']],
                    ['name' => 'icon', 'label' => 'Symbol (optional)', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Bild (optional)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'eyebrow', 'label' => 'Dachzeile oder große Zahl (optional)', 'type' => 'text', 'max' => 30, 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                    ['name' => 'link', 'label' => 'Link (ganze Kachel, optional)', 'type' => 'link'],
                ]],
            ],
        ],
        'pricing' => [
            'label' => 'Pakete / Vergleich', 'icon' => 'currency-eur', 'group' => 'Inhalt',
            'help' => 'Pakete als Karten oder als Vergleichstabelle. Ein Paket lässt sich hervorheben. Nur verbindliche, aktuelle Preise angeben.',
            'variants' => ['cards' => 'Karten', 'table' => 'Vergleichstabelle'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Pakete', 'type' => 'repeater', 'item_label' => 'Paket', 'title_field' => 'name', 'max_items' => 4, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 40, 'width' => 'half'],
                    ['name' => 'badge', 'label' => 'Etikett (optional)', 'type' => 'text', 'max' => 24, 'width' => 'half', 'placeholder' => 'z. B. Empfohlen'],
                    ['name' => 'price', 'label' => 'Preis', 'type' => 'text', 'max' => 24, 'width' => 'half', 'placeholder' => 'z. B. ab 490 €'],
                    ['name' => 'period', 'label' => 'Zeitraum / Zusatz', 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => 'z. B. pro Monat'],
                    ['name' => 'text', 'label' => 'Kurzbeschreibung', 'type' => 'textarea', 'rows' => 2, 'max' => 220],
                    ['name' => 'features', 'label' => 'Leistungen (eine pro Zeile, bei „Karten“)', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'highlight', 'label' => 'Hervorheben', 'type' => 'bool', 'default' => false],
                    ['name' => 'button_label', 'label' => 'Button – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                    ['name' => 'button_link', 'label' => 'Button – Link', 'type' => 'link', 'width' => 'half'],
                ]],
                ['name' => 'rows', 'label' => 'Vergleichszeilen (bei „Vergleichstabelle“)', 'type' => 'repeater', 'item_label' => 'Zeile', 'title_field' => 'label', 'max_items' => 20, 'fields' => [
                    ['name' => 'label', 'label' => 'Merkmal', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'values', 'label' => 'Werte je Paket (eine Zeile pro Paket, in Reihenfolge)', 'type' => 'textarea', 'rows' => 3,
                        'help' => '„ja“ oder „✓“ = enthalten, „nein“ oder „–“ = nicht enthalten, sonst freier Text (z. B. „3 Termine“).'],
                ]],
                ['name' => 'note', 'label' => 'Hinweis unter den Paketen (optional)', 'type' => 'text', 'max' => 200, 'placeholder' => 'z. B. Alle Preise inkl. MwSt.'],
            ],
        ],
        'tabs' => [
            'label' => 'Reiter (Tabs)', 'icon' => 'list-bullets', 'group' => 'Inhalt',
            'help' => 'Mehrere Inhalte auf engem Raum, umschaltbar über Reiter. „Reiter links“ stellt sich bei wenig Platz automatisch oben an. Ohne JavaScript stehen alle Inhalte untereinander.',
            'variants' => ['top' => 'Reiter oben', 'side' => 'Reiter links'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Reiter', 'type' => 'repeater', 'item_label' => 'Reiter', 'title_field' => 'label', 'max_items' => 8, 'fields' => [
                    ['name' => 'label', 'label' => 'Beschriftung des Reiters', 'type' => 'text', 'required' => true, 'max' => 40],
                    ['name' => 'text', 'label' => 'Inhalt', 'type' => 'richtext'],
                    ['name' => 'image', 'label' => 'Bild (optional, 4:3)', 'type' => 'media'],
                ]],
            ],
        ],
        'scrolly' => [
            'label' => 'Scrollytelling', 'icon' => 'article', 'group' => 'Inhalt',
            'help' => 'Ein Bild bleibt stehen, während die Texte daneben vorbeiziehen; das Bild wechselt passend zum Abschnitt. Bei wenig Platz stehen Bild und Text jeweils untereinander.',
            'variants' => ['left' => 'Bild links', 'right' => 'Bild rechts'],
            'fields' => [
                ...$head(),
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios],
                ['name' => 'items', 'label' => 'Abschnitte', 'type' => 'repeater', 'item_label' => 'Abschnitt', 'title_field' => 'title', 'max_items' => 8, 'fields' => [
                    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 90, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 4, 'max' => 600],
                    ['name' => 'image', 'label' => 'Bild', 'type' => 'media'],
                ]],
            ],
        ],
        'video' => [
            'nestable' => ['wide'],   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Video', 'icon' => 'play-circle', 'group' => 'Medien',
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung (lädt erst nach Klick, ohne Cookies bis dahin) oder eigene MP4-Datei aus dem Medienpool.',
            'variants' => ['wide' => 'Breit', 'text' => 'Mit Text daneben', 'cinema' => 'Kino (randlos, dunkel)'],
            'fields' => [
                ...$head(false),
                ['name' => 'video_url', 'label' => 'YouTube- oder Vimeo-Link', 'type' => 'url', 'width' => 'half'],
                ['name' => 'video_file', 'label' => 'oder eigene MP4-Datei', 'type' => 'file', 'width' => 'half',
                    'help' => 'Datei aus dem Medienpool (MP4, stumm oder mit Ton).'],
                ['name' => 'poster', 'label' => 'Eigenes Vorschaubild (optional)', 'type' => 'media', 'width' => 'half'],
                ['name' => 'ratio', 'label' => 'Format', 'type' => 'select', 'required' => true, 'default' => '16-9', 'width' => 'half', 'options' => ['16-9' => '16:9', '4-3' => '4:3', '21-9' => '21:9']],
                ['name' => 'caption', 'label' => 'Bildunterschrift (optional)', 'type' => 'text', 'max' => 200],
                ['name' => 'text', 'label' => 'Text daneben (bei „Mit Text daneben“)', 'type' => 'richtext'],
            ],
        ],
        'downloads' => [
            'nestable' => true,   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Downloads', 'icon' => 'download-simple', 'group' => 'Medien',
            'help' => 'Dateien aus dem Medienpool zum Herunterladen; PDFs auf Wunsch auch zum Ansehen. Größe und Typ erscheinen automatisch.',
            'fields' => [
                ...$head(),
                ['name' => 'source', 'label' => 'Quelle', 'type' => 'select', 'required' => true, 'default' => 'manual', 'width' => 'half',
                    'options' => ['manual' => 'Einzelne Dateien auswählen', 'collection' => 'Alle Dateien einer Medienkategorie']],
                ['name' => 'collection', 'label' => 'Medienkategorie', 'type' => 'mediacategory', 'width' => 'half'],
                ['name' => 'files', 'label' => 'Dateien (bei „Einzelne Dateien“)', 'type' => 'repeater', 'item_label' => 'Datei', 'title_field' => 'label', 'fields' => [
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'width' => 'half', 'help' => 'Leer = Titel der Datei'],
                    ['name' => 'file', 'label' => 'Datei', 'type' => 'file', 'width' => 'half'],
                    ['name' => 'note', 'label' => 'Hinweis (optional)', 'type' => 'text'],
                ]],
                ['name' => 'show_viewer', 'label' => 'PDFs zusätzlich „Ansehen“ anbieten (öffnet im Browser)', 'type' => 'bool', 'default' => true],
            ],
        ],
        // Website: Kontakt und Karte – Angaben aus „Website“ (FriendsOfRedaxo\Fluid\Settings, Maps)
        'contact' => [
            'label' => 'Kontakt (Karte, Formular, Zeiten)', 'icon' => 'address-book', 'group' => 'Website',
            'help' => 'Adresse, Telefon, E-Mail, Öffnungszeiten und Karte kommen aus „Website“ (Menü ⋯). Optional ein Formular aus YForm.',
            'fields' => [
                ...$head(),
                ['name' => 'show_hours', 'label' => 'Öffnungszeiten anzeigen (falls eingetragen)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_map', 'label' => 'Karte anzeigen (falls Standort eingetragen)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'form', 'label' => 'Formular (optional, aus YForm)', 'type' => 'yform_form',
                    'help' => 'Formular-Klasse im Projekt (#[AsForm]) – z. B. Rückrufwunsch oder Anfrage.'],
                ['name' => 'form_title', 'label' => 'Überschrift über dem Formular', 'type' => 'text', 'max' => 80, 'placeholder' => 'z. B. Schreiben Sie uns'],
                ['name' => 'note', 'label' => 'Hinweis unten (optional)', 'type' => 'text'],
            ],
        ],
        'map' => [
            'nestable' => true,
            'label' => 'Karte', 'icon' => 'map-trifold', 'group' => 'Website',
            'help' => 'Interaktive Karte (OpenStreetMap-Daten über den eigenen Server – ohne Einwilligung, ohne Cookies).',
            'fields' => [...$head(false, false), ...\FriendsOfRedaxo\Fluid\Maps\Maps::blockFields()],
        ],
        'search' => [
            'label' => 'Suche', 'icon' => 'magnifying-glass', 'group' => 'Website',
            'help' => 'Suchfeld mit Treffern der ganzen Website (Tippfehler-tolerant, ohne Cookies). Die Seite mit diesem Block unter „Website“ → Kopf eintragen – dann erscheint im Kopf eine Lupe.',
            'fields' => [
                ...$head(false),
                ['name' => 'placeholder', 'label' => 'Platzhalter im Suchfeld', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'Wonach suchen Sie?'],
                ['name' => 'limit', 'label' => 'Höchstens Treffer', 'type' => 'number', 'default' => 20, 'width' => 'half'],
                ['name' => 'empty_text', 'label' => 'Hinweis ohne Treffer (optional)', 'type' => 'text', 'max' => 200, 'placeholder' => 'z. B. Rufen Sie uns gern an.'],
            ],
        ],
        // YForm (optional): Formular und Datenliste aus den Tabellen des Table Managers
        'form' => [
            'requires' => 'yform',
            'nestable' => true,   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Formular', 'icon' => 'envelope-simple', 'group' => 'Daten',
            'help' => 'Formular aus YForm: Felder, Prüfungen und was nach dem Absenden passiert (speichern, E-Mail, Bestätigung) legt die Formular-Klasse im Projekt fest (#[AsForm]). Schutz vor Bots und CSRF über YForm.',
            'variants' => ['single' => 'Formular unter der Überschrift', 'split' => 'Text links, Formular rechts'],
            'fields' => [
                ...$head(),
                ['name' => 'form', 'label' => 'Formular', 'type' => 'yform_form', 'required' => true],
                ['name' => 'note', 'label' => 'Hinweis unter dem Formular (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400,
                    'help' => 'Zum Beispiel Datenschutz: wofür die Angaben verwendet werden.'],
            ],
        ],
        'datalist' => [
            'requires' => 'yform',
            'label' => 'Datenliste', 'icon' => 'rows', 'group' => 'Daten',
            'help' => 'Einträge einer YForm-Tabelle als Karten, Liste oder Tabelle – mit Detailansicht auf derselben Seite. Die Tabelle ist eine Klasse im Projekt (#[AsTable]); im Bearbeiten-Modus öffnet „Bearbeiten“ am Eintrag die Maske von YForm im Popup.',
            'variants' => ['cards' => 'Karten', 'list' => 'Liste', 'table' => 'Tabelle'],
            'fields' => [
                ...$head(),
                ['name' => 'table', 'label' => 'YForm-Tabelle', 'type' => 'yform_table', 'required' => true],
                ['name' => 'title_field', 'label' => 'Titel', 'type' => 'yform_field', 'width' => 'half', 'help' => 'Leer = erstes Textfeld'],
                ['name' => 'image_field', 'label' => 'Bild', 'type' => 'yform_field', 'width' => 'half', 'help' => 'Leer = erstes Medienfeld'],
                ['name' => 'text_field', 'label' => 'Text', 'type' => 'yform_field', 'width' => 'half', 'help' => 'Leer = erstes mehrzeiliges Feld'],
                ['name' => 'meta_field', 'label' => 'Zusatzangabe (z. B. Datum, Ort)', 'type' => 'yform_field', 'width' => 'half'],
                ['name' => 'filter_field', 'label' => 'Nur Einträge, bei denen …', 'type' => 'yform_field', 'width' => 'half', 'help' => 'z. B. „Online“'],
                ['name' => 'filter_value', 'label' => '… gleich ist', 'type' => 'text', 'max' => 100, 'width' => 'half', 'placeholder' => '1'],
                ['name' => 'sort_field', 'label' => 'Sortieren nach', 'type' => 'yform_field', 'width' => 'half', 'help' => 'Leer = Einstellung der Tabelle'],
                ['name' => 'sort_dir', 'label' => 'Reihenfolge', 'type' => 'select', 'width' => 'half', 'default' => 'asc', 'required' => true,
                    'options' => ['asc' => 'Aufsteigend (A–Z, älteste zuerst)', 'desc' => 'Absteigend (Z–A, neueste zuerst)']],
                ['name' => 'limit', 'label' => 'Höchstens (0 = alle)', 'type' => 'number', 'width' => 'half', 'default' => 0],
                $size,
                ['name' => 'detail', 'label' => 'Einträge verlinken (Detailansicht mit allen Feldern)', 'type' => 'bool', 'default' => true],
                ['name' => 'empty_text', 'label' => 'Text, wenn keine Einträge da sind', 'type' => 'text', 'max' => 160, 'placeholder' => 'Zurzeit gibt es hier keine Einträge.'],
            ],
        ],
    ],
];
