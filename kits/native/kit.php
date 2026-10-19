<?php
/*
 * Kit „NativeCSS“ – erbt vom Kit fluid ($parent). Eigener Seitenrahmen (template.view.php) und eigene Views für
 * Einstieg, Karten, Text + Bild, Handlungsaufruf und FAQ mit den Komponenten von NativeCSS; alle übrigen Blöcke kommen
 * von fluid und übernehmen über die Token-Brücke (assets/css/native.css) Farben, Schrift, Radien und Schatten.
 *
 * NativeCSS: https://github.com/skerbis/nativecss (MIT) – unverändert unter assets/ncss/ (Stand: assets/ncss/VERSION.txt).
 */

// Standardwerte im Stil von NativeCSS: tiefes Blau, Navy, blaugetönte Flächen, Systemschrift (kein Download).
// Hell und dunkel nach WCAG 2.2 AA geprüft (Akzent/Grund 8,9:1, Schrift/Grund 15:1, Nebentext/Fläche 6:1).
$colorKeys = ['accent', 'accent_strong', 'on_accent', 'highlight', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];
$light = ['#154A86', '#0F3766', '#FFFFFF', '#DCEBFF', '#0D1726', '#1F2633', '#535B68', '#FFFFFF', '#EAF3FF', '#D7E3F3', '#0B1C33'];
$dark = ['#8DB6EE', '#B5D0F6', '#0A1628', '#1E3A5F', '#EEF2F8', '#D3DAE5', '#A3AFC0', '#0B1626', '#13233A', '#2A3B57', '#132B4A'];
$defaults = array_combine($colorKeys, $light) + [
    'secondary' => '#314164', 'on_secondary' => '#FFFFFF',
    'font_body' => 'system', 'font_head' => 'system', 'heading_weight' => 700, 'heading_tracking' => -1,
    'radius' => 12, 'buttons' => 'solid', 'cards' => 'outlined', 'shadow' => 'soft', 'eyebrow' => 'plain',
];
$darkDefaults = array_combine($colorKeys, $dark) + ['secondary' => '#9DB0D8', 'on_secondary' => '#0A1628'];

$design = $parent['design'];
// „Kopf & Fuß“ gehört zum Seitenrahmen von fluid – der Rahmen dieses Kits kommt aus NativeCSS
$design['groups'] = array_values(array_filter($design['groups'], static fn (array $g) => 'navigation' !== ($g['id'] ?? '')));
foreach ($design['groups'] as &$group) {
    foreach ($group['tokens'] as &$token) {
        $name = (string) ($token['name'] ?? '');
        if (array_key_exists($name, $defaults)) {
            $token['default'] = $defaults[$name];
        }
        if (array_key_exists($name, $darkDefaults) && 'color' === ($token['type'] ?? '')) {
            $token['dark'] = $darkDefaults[$name];
        }
    }
    unset($token);
}
unset($group);

$values = static function (array $l, array $d, array $rest) use ($colorKeys): array {
    $v = array_combine($colorKeys, $l);
    foreach ($colorKeys as $i => $k) {
        $v[$k . '@dark'] = $d[$i];
    }

    return $v + $rest;
};
$design['presets'] = [
    'native' => ['label' => 'NativeCSS', 'description' => 'Tiefes Blau, Navy, blaugetönte Flächen und die Systemschrift des Geräts – wie die NativeCSS-Website.',
        'values' => $values($light, $dark, ['secondary' => '#314164', 'on_secondary' => '#FFFFFF', 'secondary@dark' => '#9DB0D8', 'on_secondary@dark' => '#0A1628',
            'font_body' => 'system', 'font_head' => 'system', 'heading_weight' => 700, 'heading_tracking' => -1, 'radius' => 12, 'eyebrow' => 'plain'])],
    'native-graphite' => ['label' => 'NativeCSS · Graphit', 'description' => 'Gedämpftes Grau als Marke, Navy als Zweitfarbe, Inter – sachlich und ruhig.',
        'values' => $values(
            ['#4A4A49', '#2F2F2E', '#FFFFFF', '#E7ECF3', '#111111', '#1F1F1F', '#55595F', '#FFFFFF', '#F2F4F7', '#DDE2E8', '#1B2233'],
            ['#C9C9C8', '#E2E2E1', '#111111', '#2E3440', '#F2F3F5', '#D6D8DC', '#A7ABB2', '#121417', '#1B1E23', '#2C3038', '#20283A'],
            ['secondary' => '#314164', 'on_secondary' => '#FFFFFF', 'secondary@dark' => '#9DB0D8', 'on_secondary@dark' => '#0A1628',
                'font_body' => 'inter', 'font_head' => 'inter', 'heading_weight' => 650, 'heading_tracking' => -2, 'radius' => 8, 'eyebrow' => 'plain'])],
] + $design['presets'];

return [
    'label' => 'NativeCSS',
    // Brücke Design-Leiste → NativeCSS und Gestaltung von Rahmen und Blöcken (Layer „native“)
    'css' => ['css/native.css'],
    'design' => $design,
    // Optionen mit eigenem Stylesheet von fluid, die auch hier wirken (Kopf/Fuß nicht – eigener Rahmen)
    'design_css' => array_values(array_diff((array) $parent['design_css'], ['header', 'footer'])),
    // Einstieg, Karten, Text + Bild, Handlungsaufruf, FAQ: eigene Views mit NativeCSS – deren CSS steht in native.css.
    // Varianten ohne eigene View (Einstieg „Karten“, „Skala“, „Collage“, „Vergleich“, Karten „Band“) nutzen die von fluid.
    'assets' => [
        'css/hero-x.css' => ['hero:cards'],
        'css/b-cards.css' => ['cards:reel'],
        'css/b-media-text.css' => ['media_text:split', 'media_text:overlap'],
        'css/b-cta.css' => ['cta:split', 'cta:big'],
        'css/b-faq.css' => [],
    ],
];
