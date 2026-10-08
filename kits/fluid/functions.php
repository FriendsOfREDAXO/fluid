<?php

/**
 * Hilfsfunktionen für die Ausgaben des Kits „fluid“.
 *
 * Die View-Dateien stammen aus KLXM Studio; die Funktionen hier bilden die dort genutzten Helfer auf REDAXO ab
 * (Medienpool statt Mediathek, REDAXO-Links, Sanitizer des Cores). Prototyp – im Paket kommen sie in einen Namespace.
 */

use FriendsOfRedaxo\Blocks\Block;
use FriendsOfRedaxo\Blocks\Editing;
use FriendsOfRedaxo\Blocks\Kit\Fluid\FluidKit;
use Redaxo\Core\Content\Article;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Util\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

use function Redaxo\Core\View\escape;

if (!function_exists('e')) {
    function e(mixed $v): string
    {
        return (string) escape((string) $v);
    }
}

/** Sichtbare Texte des Kits (Übersetzung folgt über I18n) */
function lt(string $text, array $vars = []): string
{
    foreach ($vars as $k => $v) {
        $text = str_replace('{' . $k . '}', (string) $v, $text);
    }

    return $text;
}

function __(string $text): string
{
    return $text;
}

function is_editing(): bool
{
    return Editing::active();
}

function setting(string $key, mixed $default = null): mixed
{
    return $default;
}

/** Symbol aus den Phosphor-Sprites (MIT) des Kits */
function icon(?string $name, array $opt = []): string
{
    $name = trim((string) $name);
    if ('' === $name) {
        return '';
    }
    static $map = null, $core = null;
    $map ??= json_decode((string) file_get_contents(FluidKit::instance()->assetPath('icons/icons-map.json')), true) ?: [];
    $core ??= (string) file_get_contents(FluidKit::instance()->assetPath('icons/core.svg'));
    $sprite = str_contains($core, 'id="i-' . $name . '"') ? 'core' : ($map[$name] ?? 'icons');
    $cls = trim('ico ' . ($opt['class'] ?? ''));
    $label = trim((string) ($opt['label'] ?? ''));
    $a11y = '' !== $label ? ' role="img" aria-label="' . e($label) . '"' : ' aria-hidden="true"';

    return '<svg class="' . e($cls) . '"' . $a11y . ' focusable="false" width="1em" height="1em" fill="currentColor">'
        . '<use href="' . e(FluidKit::instance()->assetUrl('icons/' . $sprite . '.svg')) . '#i-' . e($name) . '"/></svg>';
}

/** Medium aus dem Medienpool (Dateiname) */
function media(?string $name): ?array
{
    $m = $name ? Media::get($name) : null;

    return $m ? ['file' => $m->fileName, 'mime' => (string) $m->type, 'title' => $m->title, 'width' => $m->width, 'height' => $m->height, 'media' => $m] : null;
}

function media_url(array|string|null $m, ?int $width = null): string
{
    $name = is_array($m) ? (string) ($m['file'] ?? '') : (string) $m;
    $media = '' !== $name ? Media::get($name) : null;

    return $media ? $media->getUrl() : '';
}

/** Bild aus dem Medienpool (im Prototyp ohne Bildgrößen; Alt-Text = Titel im Medienpool) */
function img(?string $name, string $sizes = '100vw', array $opt = []): string
{
    $m = $name ? Media::get($name) : null;
    if (!$m) {
        return '';
    }
    $alt = $opt['alt'] ?? $m->title;
    $wh = $m->width && $m->height ? ' width="' . $m->width . '" height="' . $m->height . '"' : '';
    $eager = !empty($opt['eager']);

    return '<img src="' . e($m->getUrl()) . '" alt="' . e($alt) . '"' . $wh . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"'
        . (!empty($opt['class']) ? ' class="' . e($opt['class']) . '"' : '') . '>';
}

/** Rich Text sicher ausgeben (Sanitizer des Cores mit den Elementen der Formatierungsleiste) */
function rich(?string $html): string
{
    static $config = null;
    $config ??= new HtmlSanitizerConfig()
        ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')
        ->allowElement('u')->allowElement('s')->allowElement('h2', ['id'])->allowElement('h3', ['id'])->allowElement('h4', ['id'])
        ->allowElement('ul')->allowElement('ol')->allowElement('li')->allowElement('blockquote')->allowElement('code')
        ->allowElement('a', ['href', 'title', 'target', 'rel'])->allowElement('mark')->allowElement('sub')->allowElement('sup')
        ->allowRelativeLinks()->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])->withMaxInputLength(-1);
    $html = trim((string) $html);
    if ('' === $html) {
        return '';
    }
    // Reiner Text aus einem Textfeld → Absätze
    if (!preg_match('~<(p|h[2-4]|ul|ol|blockquote)\b~i', $html)) {
        $html = '<p>' . preg_replace('~\R{2,}~', '</p><p>', nl2br(e($html), false)) . '</p>';
    }

    return Str::sanitizeHtml($html, $config);
}

/** Link-Wert eines Feldes → Adresse: redaxo://12, page:12, 12, https://…, #anker, tel:, mailto: */
function link_href(?string $link): string
{
    $l = trim((string) $link);
    if ('' === $l) {
        return '#';
    }
    if (preg_match('~^(?:redaxo://|page:)?(\d+)(#[\w-]+)?$~', $l, $m)) {
        $art = Article::get((int) $m[1]);

        return ($art ? $art->getUrl() : '#') . ($m[2] ?? '');
    }

    return $l;
}

function is_external(string $href): bool
{
    return (bool) preg_match('~^https?://~i', $href) && !str_contains($href, (string) ($_SERVER['HTTP_HOST'] ?? '#'));
}

function ext_attrs(string $href): string
{
    return is_external($href) ? ' target="_blank" rel="noopener"' : '';
}

// ------------------------------------------------------------ aus kits/fluid/functions.php (KLXM Studio)

function fluid_link(?string $link): string
{
    return link_href($link);
}

function fluid_link_attrs(?string $link): string
{
    $href = fluid_link($link);

    return 'href="' . e($href) . '"' . ext_attrs($href);
}

function fluid_ext_note(string $href): string
{
    return is_external($href) ? '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '';
}

function fluid_buttons(Block $b, string $class = '', array $data = [], string $prefix = ''): string
{
    $d = $data ?: $b->data;
    $h = '';
    foreach (['button' => 'btn--primary', 'button2' => 'btn--secondary'] as $k => $style) {
        $label = trim((string) ($d[$k . '_label'] ?? ''));
        $link = trim((string) ($d[$k . '_link'] ?? ''));
        if ('' === $label || ('' === $link && !is_editing())) {
            continue;
        }
        $href = fluid_link($link);
        $h .= '<a class="btn ' . $style . '" ' . fluid_link_attrs($link) . '><span' . $b->edit($prefix . $k . '_label') . '>' . e($label) . '</span>' . fluid_ext_note($href) . '</a>';
    }

    return '' !== $h ? '<div class="btn-row' . ('' !== $class ? ' ' . e($class) : '') . '">' . $h . '</div>' : '';
}

function fluid_lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $text)), fn ($l) => '' !== $l));
}

function fluid_checks(array $lines, string $class = ''): string
{
    if (!$lines) {
        return '';
    }
    $h = '<ul class="checks' . ('' !== $class ? ' ' . e($class) : '') . '" role="list">';
    foreach ($lines as $l) {
        $h .= '<li>' . icon('check-circle', ['class' => 'checks__ico']) . '<span>' . e($l) . '</span></li>';
    }

    return $h . '</ul>';
}

function fluid_head(Block $b, string $class = '', string $tag = 'h2'): string
{
    $d = $b->data;
    $eyebrow = trim((string) ($d['eyebrow'] ?? ''));
    $title = trim((string) ($d['title'] ?? ''));
    $intro = trim((string) ($d['intro'] ?? ''));
    if ('' === $eyebrow && '' === $title && '' === $intro && !is_editing()) {
        return '';
    }
    $h = '<header class="sec-head' . ('' !== $class ? ' ' . e($class) : '') . '">';
    if ('' !== $eyebrow || is_editing()) {
        $h .= '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . e($eyebrow) . '</p>';
    }
    if ('' !== $title || is_editing()) {
        $h .= '<' . $tag . ' id="' . e($b->titleId()) . '" class="h2"' . $b->edit('title') . '>' . e($title) . '</' . $tag . '>';
    }
    if ('' !== $intro || (is_editing() && array_key_exists('intro', $d))) {
        $h .= '<p class="lead"' . $b->edit('intro', 'lines') . '>' . nl2br(e($intro), false) . '</p>';
    }

    return $h . '</header>';
}

function fluid_htag(array $d): string
{
    return '' !== trim((string) ($d['title'] ?? '')) ? 'h3' : 'h2';
}

function fluid_min(array $d, string $key = 'size'): string
{
    return 'min-' . (in_array($d[$key] ?? '', ['s', 'm', 'l'], true) ? $d[$key] : 'm');
}

function fluid_image(?string $id, string $sizes, string $ratio, string $class = '', array $opt = []): string
{
    $pic = img($id, $sizes, ('' !== $ratio ? ['ratio' => $ratio] : []) + $opt);
    $cls = 'frame' . ('' !== $ratio ? ' r-' . str_replace(':', '-', $ratio) : '') . ('' !== $class ? ' ' . $class : '');
    if ('' !== $pic) {
        return '<div class="' . e($cls) . '">' . $pic . '</div>';
    }

    return is_editing() ? '<div class="' . e($cls) . ' frame--empty"><span>' . e('Bild' . ('' !== $ratio ? ' · ' . $ratio : '')) . '</span></div>' : '';
}

function fluid_title(string $text): string
{
    if (is_editing()) {
        return e($text);
    }

    return preg_replace('~\*([^*]+)\*~u', '<em class="hl">$1</em>', e($text)) ?? e($text);
}

function fluid_toc(string $html, string $prefix): array
{
    $toc = [];
    $used = [];
    $html = preg_replace_callback('~<(h[34])([^>]*)>(.*?)</\1>~is', static function ($m) use (&$toc, &$used, $prefix) {
        $label = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ('' === $label) {
            return $m[0];
        }
        if (preg_match('~\sid="([^"]+)"~', $m[2], $idm)) {
            $id = $idm[1];
            $attrs = $m[2];
        } else {
            $base = $prefix . '-' . (trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: '')), '-') ?: 'abschnitt');
            $id = $base;
            for ($i = 2; isset($used[$id]); ++$i) {
                $id = $base . '-' . $i;
            }
            $attrs = $m[2] . ' id="' . e($id) . '"';
        }
        $used[$id] = true;
        $toc[] = ['id' => $id, 'label' => $label, 'level' => 'h3' === strtolower($m[1]) ? 2 : 3];

        return '<' . $m[1] . $attrs . '>' . $m[3] . '</' . $m[1] . '>';
    }, $html) ?? $html;

    return [$html, $toc];
}

function fluid_reading_time(string $html): int
{
    return max(1, (int) round(str_word_count(strip_tags($html), 0, 'äöüÄÖÜß') / 220));
}

/** „Wert: Bezeichnung“ je Zeile (Hero „Fließende Skala“) */
function fluid_pairs(mixed $s): array
{
    return array_map(static function (string $l): array {
        [$a, $b] = str_contains($l, ':') ? array_map('trim', explode(':', $l, 2)) : ['', $l];

        return ['label' => $a, 'value' => $b];
    }, fluid_lines((string) $s));
}

function fluid_compare(array $d, string $sizes, string $ratio, string $class): string
{
    return '';
}
