<?php

/**
 * Hilfsfunktionen für die Ausgaben des Kits „fluid“.
 *
 * Die View-Dateien stammen aus KLXM Studio; die Funktionen hier bilden die dort genutzten Helfer auf REDAXO ab
 * (Medienpool statt Mediathek, REDAXO-Links, Sanitizer des Cores). Prototyp – im Paket kommen sie in einen Namespace.
 */

use FriendsOfRedaxo\Fluid\Block;
use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidKit;
use Redaxo\Core\Content\Article;
use Redaxo\Core\MediaPool\Media;

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

/** Website-Einstellungen (FriendsOfRedaxo\Fluid\Settings) – wie setting() in KLXM Studio */
function setting(string $key, mixed $default = null): mixed
{
    return \FriendsOfRedaxo\Fluid\Settings::get($key, $default);
}

function filled(mixed $v): bool
{
    return null !== $v && '' !== trim((string) (is_array($v) ? implode('', $v) : $v)) && !str_starts_with(trim((string) (is_array($v) ? '' : $v)), '[');
}

// ------------------------------------------------------------ Website: Name, Kontakt, Zeiten, Social (aus kits/fluid/functions.php)

function fluid_name(bool $short = false): string
{
    return \FriendsOfRedaxo\Fluid\Settings::name($short);
}

function fluid_phone(): string
{
    return trim((string) setting('phone', ''));
}

/** tel:-Adresse aus einer Telefonnummer (+49 statt 0) oder null */
function fluid_phone_href(): ?string
{
    $p = preg_replace('~[^\d+]~', '', fluid_phone());
    if ('' === $p || strlen($p) < 4) {
        return null;
    }
    if (str_starts_with($p, '00')) {
        $p = '+' . substr($p, 2);
    } elseif (str_starts_with($p, '0')) {
        $p = '+49' . substr($p, 1);
    }

    return 'tel:' . $p;
}

function fluid_email(): string
{
    $m = trim((string) setting('email', ''));

    return filter_var($m, FILTER_VALIDATE_EMAIL) ? $m : '';
}

/** Adresse als Zeilen: [Straße, PLZ Ort, Land] – nur befüllte Teile */
function fluid_address_lines(): array
{
    $city = trim(trim((string) setting('zip', '')) . ' ' . trim((string) setting('city', '')));

    return array_values(array_filter([trim((string) setting('street', '')), $city, trim((string) setting('country', ''))], 'filled'));
}

function fluid_clock(string $t): string
{
    return preg_match('~^(\d{1,2}):(\d{2})~', $t, $m) ? ((int) $m[1]) . ('00' === $m[2] ? '' : ':' . $m[2]) : $t;
}

function fluid_time_range(array $r): string
{
    $seg = [];
    if (!empty($r['von']) && !empty($r['bis'])) {
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $seg[] = fluid_clock($r['von']) . '–' . fluid_clock($r['pause_von']);
            $seg[] = fluid_clock($r['pause_bis']) . '–' . fluid_clock($r['bis']);
        } else {
            $seg[] = fluid_clock($r['von']) . '–' . fluid_clock($r['bis']);
        }
    }
    $time = $seg ? implode(', ', $seg) . ' Uhr' : '';

    return implode(' · ', array_filter([$time, trim((string) ($r['notiz'] ?? ''))]));
}

function fluid_weekday(int $dow, bool $short = false): string
{
    $l = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][$dow] ?? '';

    return $short ? mb_substr($l, 0, 2) : $l;
}

/** Öffnungszeiten Mo–So, aufeinanderfolgende Tage mit gleichen Zeiten zusammengefasst @return list<array{days: string, time: string}> */
function fluid_hours(): array
{
    $byDay = [];
    foreach ((array) setting('hours', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if ($dow < 0 || $dow > 6) {
            continue;
        }
        $t = fluid_time_range($r);
        if ('' !== $t) {
            $byDay[$dow][] = $t;
        }
    }
    $groups = [];
    foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
        if (!isset($byDay[$dow])) {
            $groups[] = null;
            continue;
        }
        $time = implode(' · ', $byDay[$dow]);
        $last = $groups ? $groups[array_key_last($groups)] : null;
        if ($last && $last['time'] === $time) {
            $groups[array_key_last($groups)]['to'] = $dow;
        } else {
            $groups[] = ['from' => $dow, 'to' => $dow, 'time' => $time];
        }
    }
    $out = [];
    foreach (array_filter($groups) as $g) {
        $out[] = ['days' => fluid_weekday($g['from'], $g['to'] !== $g['from']) . ($g['to'] !== $g['from'] ? '–' . fluid_weekday($g['to'], true) : ''), 'time' => $g['time']];
    }

    return $out;
}

function fluid_social(): array
{
    return array_values(array_filter((array) setting('social', []), static fn ($s) => '' !== trim((string) ($s['label'] ?? '')) && preg_match('~^https?://~i', (string) ($s['url'] ?? ''))));
}

function fluid_quick_contact(string $class = 'quick'): string
{
    $phone = fluid_phone();
    $tel = fluid_phone_href();
    $email = fluid_email();
    if (!($tel && filled($phone)) && '' === $email) {
        return '';
    }
    $h = '<ul class="' . e($class) . '" role="list">';
    if ($tel && filled($phone)) {
        $h .= '<li><a href="' . e($tel) . '">' . icon('phone') . '<span>' . e($phone) . '</span></a></li>';
    }
    if ('' !== $email) {
        $h .= '<li><a href="mailto:' . e($email) . '">' . icon('envelope-simple') . '<span>' . e($email) . '</span></a></li>';
    }

    return $h . '</ul>';
}

/** Impressum, Datenschutz, Barrierefreiheit (Website → Recht) @return list<array{label: string, href: string}> */
function fluid_legal_links(): array
{
    $out = [];
    foreach (['imprint_page' => 'Impressum', 'privacy_page' => 'Datenschutz', 'accessibility_page' => 'Barrierefreiheit'] as $k => $label) {
        $l = trim((string) setting($k, ''));
        if ('' !== $l) {
            $out[] = ['label' => lt($label), 'href' => link_href($l)];
        }
    }

    return $out;
}

function fluid_header_cta(): ?array
{
    $label = trim((string) setting('header_cta_label', ''));
    $link = trim((string) setting('header_cta_link', ''));

    return '' !== $label && '' !== $link ? ['label' => $label, 'link' => $link] : null;
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

/**
 * Bild aus dem Medienpool. Mit dem Addon image_editor: bearbeitete Fassung, responsiv (srcset, AVIF/WebP über den Media Manager)
 * und im Bearbeiten-Modus der Knopf „Bild bearbeiten“ (data-ie-file). Alt-Text = Titel im Medienpool.
 */
function img(?string $name, string $sizes = '100vw', array $opt = []): string
{
    $m = $name ? Media::get($name) : null;
    if (!$m) {
        return '';
    }
    $alt = $opt['alt'] ?? $m->title;
    $eager = !empty($opt['eager']);
    $editor = class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditor::class) ? \FriendsOfRedaxo\ImageEditor\ImageEditor::class : null;
    $editable = $editor && null === $editor::editable($m);
    if ($editable) {
        [$w, $h] = $editor::size($m->fileName);
        $src = $editor::url($m->fileName, 1280);
        $srcset = ' srcset="' . e($editor::srcset($m->fileName)) . '" sizes="' . e($sizes) . '"';
    } else {
        [$w, $h] = [(int) $m->width, (int) $m->height];
        $src = $m->getUrl();
        $srcset = '';
    }
    $wh = $w && $h ? ' width="' . $w . '" height="' . $h . '"' : '';
    $ie = $editable && is_editing() ? ' data-ie-file="' . e($m->fileName) . '"' : '';
    // Fokuspunkt und Einpassen (image_editor): object-position / object-fit am Bild
    $style = $editable ? $editor::imgStyle($m->fileName) : '';

    return '<img src="' . e($src) . '"' . $srcset . ' alt="' . e($alt) . '"' . $wh . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"'
        . (!empty($opt['class']) ? ' class="' . e($opt['class']) . '"' : '') . ('' !== $style ? ' style="' . e($style) . '"' : '') . $ie . '>';
}

/** Rich Text sicher ausgeben (Klassenvertrag der Formatierungsleiste, RichText) */
function rich(?string $html): string
{
    return \FriendsOfRedaxo\Fluid\RichText::html($html);
}

/** Link-Wert eines Feldes → Adresse: redaxo://12, page:12, 12, https://…, #anker, tel:, mailto: */
function link_href(?string $link): string
{
    $l = trim((string) $link);
    if ('' === $l) {
        return '#';
    }
    // Sonderwerte aus „Website“: phone, email
    if ('phone' === $l) {
        return fluid_phone_href() ?? '#';
    }
    if ('email' === $l) {
        return '' !== fluid_email() ? 'mailto:' . fluid_email() : '#';
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
    // Einpassen „Originalformat“ (image_editor): Rahmen ohne festes Seitenverhältnis
    if ($id && class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditor::class) && 'original' === (\FriendsOfRedaxo\ImageEditor\ImageEditor::stored($id)['fit'] ?? '')) {
        $ratio = '';
    }
    $pic = img($id, $sizes, ('' !== $ratio ? ['ratio' => $ratio] : []) + $opt);
    $cls = 'frame' . ('' !== $ratio ? ' r-' . str_replace(':', '-', $ratio) : '') . ('' !== $class ? ' ' . $class : '');
    if ('' !== $pic) {
        return '<div class="' . e($cls) . '">' . $pic . '</div>';
    }

    return is_editing() ? '<div class="' . e($cls) . ' frame--empty"><span>' . e('Bild' . ('' !== $ratio ? ' · ' . $ratio : '')) . '</span></div>' : '';
}

function fluid_title(string $text): string
{
    // Bearbeiten: Hervorhebung sichtbar, Sternchen dezent – beim Auslesen (innerText) bleibt „*Wort*“ erhalten
    if (is_editing()) {
        return preg_replace('~\*([^*]+)\*~u', '<span class="hl-mark">*</span><em class="hl">$1</em><span class="hl-mark">*</span>', e($text)) ?? e($text);
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

/** Text ohne *Betonung*-Sternchen (Attribute, Screenreader) */
function strip_emphasis(string $text): string
{
    return (string) preg_replace('~\*([^*]+)\*~u', '$1', $text);
}

/** Datenschutzerklärung: Artikel „Datenschutz“ der Struktur, sonst leer */
function privacy_url(): string
{
    static $url = null;
    if (null === $url) {
        $url = '' !== trim((string) setting('privacy_page', '')) ? link_href((string) setting('privacy_page')) : '';
        foreach ('' !== $url ? [] : \Redaxo\Core\Content\Article::getRootArticles(true) as $a) {
            if (preg_match('~^datenschutz~i', $a->name)) {
                $url = $a->getUrl();
                break;
            }
        }
    }

    return $url;
}

/**
 * Video einbetten (aus KLXM Studio, Kern-Fragment video-embed – vereinfacht): YouTube/Vimeo mit Zwei-Klick-Lösung
 * (Player lädt erst nach Klick, js/embed.js; „künftig direkt laden“ im Browser), MP4 aus dem Medienpool als <video>.
 * Markup und Klassen (.vembed …) wie in Studio, damit css/b-video.css greift.
 */
function fluid_video_embed(string $url, string $file, string $poster, string $ratio, string $label): string
{
    $ratio = in_array($ratio, ['16-9', '4-3', '21-9'], true) ? $ratio : '16-9';
    $mp4 = '' !== $file ? Media::get($file) : null;
    if ($mp4 && str_starts_with((string) $mp4->type, 'video/')) {
        $p = '' !== $poster ? Media::get($poster) : null;

        return '<div class="vembed r-' . e($ratio) . '"><video controls preload="none" playsinline' . ($p ? ' poster="' . e($p->getUrl()) . '"' : '') . '>'
            . '<source src="' . e($mp4->getUrl()) . '" type="' . e((string) $mp4->type) . '"></video></div>';
    }
    $u = trim($url);
    $v = null;
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([\w-]{11})~', $u, $m)) {
        $v = ['provider' => 'youtube', 'label' => 'YouTube', 'company' => 'Google Ireland Ltd.',
            'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0', 'watch' => 'https://www.youtube.com/watch?v=' . $m[1]];
    } elseif (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $u, $m)) {
        $v = ['provider' => 'vimeo', 'label' => 'Vimeo', 'company' => 'Vimeo.com Inc.',
            'src' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1', 'watch' => 'https://vimeo.com/' . $m[1]];
    }
    if (!$v) {
        return is_editing() ? '<div class="vembed vembed--empty r-' . e($ratio) . '"><p>YouTube-/Vimeo-Link oder MP4-Datei in der Seitenleiste eintragen.</p></div>' : '';
    }
    $title = '' !== $label ? $label : lt('Video');
    $privacy = privacy_url();
    $text = e(lt('Beim Abspielen lädt {provider} ({company}) das Video. Dabei werden Daten wie Ihre IP-Adresse an {provider} übertragen.', ['provider' => $v['label'], 'company' => $v['company']]))
        . ('' !== $privacy ? ' <a href="' . e($privacy) . '">' . e(lt('Datenschutz')) . '</a>' : '');
    $posterImg = '' !== $poster ? img($poster, '(min-width: 1080px) 1200px, 100vw', ['alt' => '', 'class' => 'vembed__poster']) : '';

    return '<div class="vembed r-' . e($ratio) . '" data-embed="' . e($v['provider']) . '" data-label="' . e($v['label']) . '" data-src="' . e($v['src']) . '" data-title="' . e($title) . '"'
        . ' data-t-revoke="' . e(lt('Freigabe widerrufen')) . '" data-t-direct="' . e(lt('{provider}-Videos werden auf dieser Website direkt geladen.', ['provider' => $v['label']])) . '">'
        . $posterImg
        . '<div class="vembed__gate"><div class="vembed__info"><p class="vembed__text">' . $text . '</p>'
        . '<p class="vembed__row"><label class="vembed__remember"><input type="checkbox" data-embed-remember> ' . e(lt('{provider}-Videos künftig direkt laden', ['provider' => $v['label']])) . '</label>'
        . '<a class="vembed__ext" href="' . e($v['watch']) . '" target="_blank" rel="noopener">' . e(lt('Auf {provider} ansehen', ['provider' => $v['label']])) . '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span></a></p></div>'
        . '<button type="button" class="vembed__play" data-embed-play><span class="vembed__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg></span>'
        . '<span>' . e(lt('{provider}-Video laden', ['provider' => $v['label']])) . '<span class="sr-only">: ' . e($title) . '</span></span></button></div></div>';
}
