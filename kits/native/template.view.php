<?php
/*
 * Seitenrahmen des Kits „NativeCSS“: Topbar mit Navigation (dialog als Off-Canvas, Untermenüs mit details),
 * Footer im Raster – Komponenten von NativeCSS. Gleiche Daten und Funktionen wie der Rahmen von fluid
 * (kits/fluid/src/FluidTemplate.view.php): Menü, Sprachen, Suche, Button im Kopf, Hinweisleiste, Blog, Editor.
 *
 * Cascade Layers (Reihenfolge im <style> unten): tokens < reset < base (NativeCSS) < fluid (Grund-CSS von fluid für die
 * Blöcke) < helpers < components (NativeCSS) < native (Brücke und Anpassungen, css/native.css). CSS ohne Layer – Design-
 * Werte, CSS je Block, Design-Optionen – liegt darüber.
 */

use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\Kit;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidTemplate;
use FriendsOfRedaxo\Fluid\PageSettings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Language\Language;

return static function (ArticleContent $content): void {
    $kit = Kit::active();
    // Inhalt zuerst: so ist bekannt, welche Blöcke (und damit welches CSS) die Seite braucht
    $main = $content->renderContent();
    $article = $content->article;
    $name = \FriendsOfRedaxo\Fluid\Settings::name();
    $landing = \FriendsOfRedaxo\Fluid\Routing\Router::landing();
    $home = $landing ? (Article::get((int) $landing->articleId)?->getUrl() ?? '/') : (Article::getSiteStartArticle()?->getUrl() ?? '/');
    $isHome = $landing ? $article->id === $landing->articleId : $article->id === Article::getSiteStartArticleId();
    $menu = FluidTemplate::menu();
    if ('' !== ($searchHref = trim((string) setting('search_page', '')))) {
        $searchHref = rtrim((string) parse_url(link_href($searchHref), PHP_URL_PATH), '/');
        $menu = array_values(array_filter($menu, static fn (array $m) => rtrim((string) parse_url((string) $m['href'], PHP_URL_PATH), '/') !== $searchHref || !empty($m['children'])));
    }
    $langs = \FriendsOfRedaxo\Fluid\Routing\Router::alternates($article);
    $title = PageSettings::title($article, $name);
    $design = $kit->design();
    $isPost = null !== \FriendsOfRedaxo\Fluid\Blog\Blog::blogOf($article);
    if ($isPost) {
        $kit->markUsed('@post');
    }
    $fluidCss = Kit::get(Kit::DEFAULT)?->assetUrl('css/site.css') ?? '';
    $css = [$kit->assetUrl('ncss/ncss.css'), ...$kit->designCss(), ...$kit->blockCss(Editing::active()), ...$kit->kitCss()];
    $designClasses = $design->classes();
    $bg = (string) $design->get('background');
    $rgb = sscanf($bg, '#%02x%02x%02x') ?: [255, 255, 255];
    $extra = (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255 < 0.18 ? ' is-darkbase' : '';
    $extra .= in_array((string) $design->get('font_head'), ['fraunces', 'newsreader', 'instrument-serif', 'roboto-slab'], true) ? ' head-serif' : '';

    $logo = (string) setting('logo', '');
    $logoDark = (string) setting('logo_dark', '');
    $brand = static function (string $class) use ($isHome, $home, $name, $logo, $logoDark): string {
        $light = '' !== $logo ? img($logo, '240px', ['eager' => true, 'alt' => '', 'class' => 'nv-brand__img']) : '';
        $dark = '' !== $light && '' !== $logoDark ? img($logoDark, '240px', ['eager' => true, 'alt' => '', 'class' => 'nv-brand__img']) : '';
        $h = '<a class="nv-brand ' . $class . '" href="' . e($isHome ? '#main' : $home) . '">';
        if ('' !== $light) {
            $h .= '<span class="nv-brand__logo' . ('' !== $dark ? ' nv-brand__logo--light' : '') . '">' . $light . '</span>'
                . ('' !== $dark ? '<span class="nv-brand__logo nv-brand__logo--dark">' . $dark . '</span>' : '') . '<span class="sr-only">' . e($name) . '</span>';
        } else {
            $h .= '<span class="nv-brand__mark" aria-hidden="true">' . e(mb_strtoupper(mb_substr(fluid_name(true), 0, 1))) . '</span><span class="nv-brand__name">' . e(fluid_name(true)) . '</span>';
        }

        return $h . '<span class="sr-only"> – ' . e(lt('Startseite')) . '</span></a>';
    };
    // Menüpunkt: Link oder Untermenü (details) mit „Übersicht“ als erstem Eintrag – Muster von NativeCSS
    $navItem = static function (array $m): string {
        $cur = $m['current'] ? ' aria-current="page"' : '';
        if (!$m['children']) {
            return '<li class="ncss-nav-item"><a href="' . e($m['href']) . '"' . $cur . ($m['active'] ? ' data-active' : '') . '>' . e($m['label']) . '</a></li>';
        }
        $h = '<li class="ncss-nav-item"><details class="ncss-nav-dropdown"' . ($m['active'] ? ' data-active' : '') . '><summary>' . e($m['label']) . '</summary><ul class="ncss-nav-submenu">'
            . '<li><a href="' . e($m['href']) . '"' . $cur . '>' . e(lt('Übersicht: {name}', ['name' => $m['label']])) . '</a></li>';
        foreach ($m['children'] as $c) {
            $h .= '<li><a href="' . e($c['href']) . '"' . ($c['current'] ? ' aria-current="page"' : '') . '>' . e($c['label']) . '</a></li>';
        }

        return $h . '</ul></details></li>';
    };
    $langList = static function (string $class) use ($langs): string {
        if (\count($langs) < 2) {
            return '';
        }
        $h = '<ul class="langswitch ' . $class . '" role="list" aria-label="' . e(lt('Sprache')) . '">';
        foreach ($langs as $l) {
            $h .= '<li><a href="' . e($l['url']) . '" hreflang="' . e($l['code']) . '" lang="' . e($l['code']) . '" title="' . e($l['name']) . '"' . ($l['current'] ? ' aria-current="true"' : '') . '>' . e(strtoupper($l['code'])) . '</a></li>';
        }

        return $h . '</ul>';
    };
    $cta = fluid_header_cta();
    $notice = !empty(setting('notice_active')) && '' !== trim((string) setting('notice_text', '')) ? trim((string) setting('notice_text')) : '';
    $address = fluid_address_lines();
    $social = fluid_social();
    $legal = fluid_legal_links();
    $tagline = trim((string) setting('tagline', ''));
    $ftrText = trim((string) setting('footer_text', ''));
    $searchPage = trim((string) setting('search_page', ''));
?><!doctype html>
<html lang="<?= e(Language::getCurrent()->code) ?>" class="no-js kit-native <?= e($designClasses . $extra) ?>" data-fluid-classes="<?= e($designClasses) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="color-scheme" content="light dark">
<?= PageSettings::head($article, $name) ?>
<style>@layer wa-native, wa-base, wa-utilities, wa-color-palette, wa-color-variant, wa-theme, wa-theme-dimension, wa-theme-overrides, tokens, reset, base, fluid, helpers, components, webawesome-bridge, browser-fixes, native;
<?php if ('' !== $fluidCss): ?>@import url("<?= e($fluidCss) ?>") layer(fluid);<?php endif; ?></style>
<?php foreach ($css as $href): ?><link rel="stylesheet" href="<?= e($href) ?>"<?= str_contains($href, '/opt-') ? ' data-fluid-opt' : '' ?>>
<?php endforeach; ?>
<?= $design->head() ?>
<script src="<?= e($kit->assetUrl('ncss/js/command-fallback.js')) ?>" defer></script>
<script src="<?= e($kit->assetUrl('ncss/js/nav.js')) ?>" defer></script>
<?php if (!Editing::active()): ?><script src="<?= e($kit->assetUrl('js/site.js')) ?>" defer></script>
<?php foreach ($kit->blockJs() as $js): ?><script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
<?php endif; ?>
</head>
<body class="<?= $isHome ? 'is-home' : 'is-sub' ?>">
<a class="skip" id="top" href="#main"><?= e(lt('Zum Inhalt springen')) ?></a>
<div class="page">
<?php if ('' !== $notice): ?><div class="topnote" role="note"><div class="wrap"><?= fluid_title($notice) ?></div></div><?php endif; ?>
<header class="ncss-topbar nv-topbar" data-header>
  <div class="ncss-topbar-inner">
    <?= $brand('ncss-topbar-brand') ?>
    <?php if ($menu): ?>
    <nav class="ncss-nav nv-nav" aria-label="<?= e(lt('Hauptnavigation')) ?>">
      <button type="button" class="ncss-nav-toggle" command="show-modal" commandfor="nv-menu" aria-haspopup="dialog" aria-label="<?= e(lt('Menü')) ?>"><span class="ncss-icon ncss-icon-menu" aria-hidden="true"></span></button>
      <dialog id="nv-menu" class="ncss-nav-panel ncss-offcanvas ncss-offcanvas--start" aria-label="<?= e(lt('Menü')) ?>">
        <button type="button" class="ncss-dialog-close" command="close" commandfor="nv-menu" aria-label="<?= e(lt('Menü schließen')) ?>"><span class="ncss-icon ncss-icon-close" aria-hidden="true"></span></button>
        <ul class="ncss-nav-list"><?= implode('', array_map($navItem, $menu)) ?></ul>
        <?= $langList('nv-nav__langs') ?>
      </dialog>
    </nav>
    <?php endif; ?>
    <div class="ncss-topbar-actions">
      <?= $langList('nv-langs') ?>
      <?php if ('' !== $searchPage): ?><a class="nv-icon-link" href="<?= e(link_href($searchPage)) ?>" title="<?= e(lt('Suche')) ?>"><?= icon('magnifying-glass') ?><span class="sr-only"><?= e(lt('Suche')) ?></span></a><?php endif; ?>
      <?php if ($cta): ?><a class="ncss-btn ncss-btn--primary nv-cta" <?= fluid_link_attrs($cta['link']) ?>><?= e($cta['label']) ?></a><?php endif; ?>
    </div>
  </div>
</header>
<div class="page__main">
<main id="main" tabindex="-1">
<?= $isPost ? fluid_post_header($article) : '' ?>
<?= $main ?>
<?= $isPost ? fluid_post_footer($article) : '' ?>
</main>
<footer class="ncss-footer nv-footer">
  <div class="ncss-footer-inner ncss-grid" style="--ncss-grid-min: 13rem;">
    <div class="ncss-stack nv-footer__about">
      <?= $brand('nv-brand--footer') ?>
      <?php if ('' !== $tagline): ?><p class="nv-footer__tagline"><?= fluid_title($tagline) ?></p><?php endif; ?>
      <?php if ('' !== $ftrText): ?><p class="ncss-text-sm"><?= nl2br(fluid_title($ftrText), false) ?></p><?php endif; ?>
    </div>
    <?php if ($address || '' !== fluid_phone() || '' !== fluid_email()): ?>
    <div class="ncss-stack">
      <h2 class="nv-footer__h"><?= e(lt('Kontakt')) ?></h2>
      <?php if ($address): ?><address class="nv-footer__address"><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
      <?= fluid_quick_contact('nv-footer__list') ?>
    </div>
    <?php endif; ?>
    <?php if ($menu): ?>
    <div class="ncss-stack">
      <h2 class="nv-footer__h" id="nv-footer-pages"><?= e(lt('Seiten')) ?></h2>
      <nav class="ncss-nav ncss-nav--tree" aria-labelledby="nv-footer-pages"><ul class="ncss-nav-list"><?php foreach ($menu as $m): ?><li class="ncss-nav-item"><a href="<?= e($m['href']) ?>"<?= $m['current'] ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul></nav>
    </div>
    <?php endif; ?>
    <?php if ($social): ?>
    <div class="ncss-stack">
      <h2 class="nv-footer__h" id="nv-footer-social"><?= e(lt('Folgen Sie uns')) ?></h2>
      <nav class="ncss-nav ncss-nav--tree" aria-labelledby="nv-footer-social"><ul class="ncss-nav-list"><?php foreach ($social as $so): ?><li class="ncss-nav-item"><a href="<?= e($so['url']) ?>" target="_blank" rel="noopener me"><?= e($so['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?></ul></nav>
    </div>
    <?php endif; ?>
  </div>
  <div class="ncss-footer-bottom nv-footer__bottom">
    <p>© <?= e(date('Y')) ?> <?= e($name) ?></p>
    <?php if ($legal): ?><nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul class="ncss-cluster nv-footer__legal" role="list"><?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
  </div>
</footer>
</div>
</div>
<link rel="stylesheet" href="<?= e($kit->assetUrl('css/overlay.css')) ?>">
</body>
</html>
<?php };
