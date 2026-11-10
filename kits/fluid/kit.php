<?php
/*
 * Kit „fluid“ für friendsofredaxo/fluid – übernommen aus KLXM Studio (MIT): breakpointlos, Container-Queries,
 * fließende Schrift- und Abstandsstufen. Die Blöcke stehen als Klassen mit Feld-Attributen in src/Module/,
 * ihre Ausgaben in views/{typ}.php.
 */

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
        'css/b-chart.css' => ['chart'],
        'css/b-media.css' => ['gallery', 'slider'],
        'js/media.js' => ['gallery', 'slider'],
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
        'js/formsteps.js' => ['form'],
        'css/data.css' => ['datalist', 'posts'],
        'css/b-posts.css' => ['posts', '@post', 'datalist'],
        'js/tabs.js' => ['tabs'],
        'css/b-code.css' => ['code'],
        'js/code.js' => ['code'],
        'js/reel.js' => ['quote', 'cards'],
        'js/scrolly.js' => ['scrolly'],
    ],
];
