<?php

use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidKit;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidTemplate;
use FriendsOfRedaxo\Fluid\PageSettings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Core;
use Redaxo\Core\Language\Language;

return static function (ArticleContent $content): void {
    $kit = FluidKit::instance();
    // Inhalt zuerst: so ist bekannt, welche Blöcke (und damit welches CSS) die Seite braucht
    $main = $content->renderContent();
    $article = $content->article;
    $name = \FriendsOfRedaxo\Fluid\Settings::name();
    $isHome = $article->id === Article::getSiteStartArticleId();
    // Landing-Domain: Einstiegsseite der Landingpage statt Startseite der Website
    $landing = \FriendsOfRedaxo\Fluid\Routing\Router::landing();
    $home = $landing ? (Article::get((int) $landing->articleId)?->getUrl() ?? '/') : (Article::getSiteStartArticle()?->getUrl() ?? '/');
    $isHome = $landing ? $article->id === $landing->articleId : $isHome;
    $menu = FluidTemplate::menu();
    // Sprachwahl: Sprachen, in denen diese Seite online ist
    $langs = \FriendsOfRedaxo\Fluid\Routing\Router::alternates($article);
    $title = PageSettings::title($article, $name);
    $design = $kit->design();
    // Blog-Beitrag: Kopf (Titel, Datum, Bild) und „Älterer/Neuerer Beitrag“ aus dem Template
    $isPost = null !== \FriendsOfRedaxo\Fluid\Blog\Blog::blogOf($article);
    if ($isPost) {
        $kit->markUsed('@post');
    }
    $css = [$kit->assetUrl('css/site.css'), ...$kit->designCss(), ...$kit->blockCss(Editing::active())];
    $designClasses = $design->classes();
    // dunkler Grundton bzw. Serifen-Überschriften (wie fluid_html_class in Studio)
    $bg = (string) $design->get('background');
    $rgb = sscanf($bg, '#%02x%02x%02x') ?: [255, 255, 255];
    $extra = (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255 < 0.18 ? ' is-darkbase' : '';
    $extra .= in_array((string) $design->get('font_head'), ['fraunces', 'newsreader', 'instrument-serif', 'roboto-slab'], true) ? ' head-serif' : '';
    $header = (string) $design->get('header') ?: 'inline';
    $footer = (string) $design->get('footer') ?: 'columns';
    $navItem = static function (array $m): string {
        $cur = $m['current'] ? ' aria-current="page"' : '';
        if (!$m['children']) {
            return '<li class="hnav__item"><a class="hnav__link" href="' . e($m['href']) . '"' . $cur . ($m['active'] ? ' data-active' : '') . '>' . e($m['label']) . '</a></li>';
        }
        $h = '<li class="hnav__item"><details class="hnav__sub"' . ($m['active'] ? ' data-active' : '') . '><summary class="hnav__link"><span>' . e($m['label']) . '</span>'
            . icon('caret-down', ['class' => 'hnav__chev']) . '</summary><ul class="hnav__panel" role="list">'
            . '<li><a class="hnav__sublink hnav__sublink--parent" href="' . e($m['href']) . '"' . $cur . '>' . e(lt('Übersicht: {name}', ['name' => $m['label']])) . '</a></li>';
        foreach ($m['children'] as $c) {
            $h .= '<li><a class="hnav__sublink" href="' . e($c['href']) . '"' . ($c['current'] ? ' aria-current="page"' : '') . '>' . e($c['label']) . '</a></li>';
        }

        return $h . '</ul></details></li>';
    };
    // Logo (hell/dunkel) oder Wortmarke – Website-Einstellungen (wie Kern-Fragment „brand“ in KLXM Studio)
    $logo = (string) setting('logo', '');
    $logoDark = (string) setting('logo_dark', '');
    $brand = static function (string $class = '') use ($isHome, $home, $name, $logo, $logoDark): string {
        $light = '' !== $logo ? img($logo, '240px', ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
        $dark = '' !== $light && '' !== $logoDark ? img($logoDark, '240px', ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
        $h = '<a class="brand' . ('' !== $class ? ' ' . $class : '') . '" href="' . e($isHome ? '#main' : $home) . '">';
        if ('' !== $light) {
            $h .= '<span class="brand__logo' . ('' !== $dark ? ' brand__logo--light' : '') . '">' . $light . '</span>'
                . ('' !== $dark ? '<span class="brand__logo brand__logo--dark">' . $dark . '</span>' : '') . '<span class="sr-only">' . e($name) . '</span>';
        } else {
            $h .= '<span class="brand__mark" aria-hidden="true">' . e(mb_strtoupper(mb_substr(fluid_name(true), 0, 1))) . '</span><span class="brand__name">' . e(fluid_name(true)) . '</span>';
        }

        return $h . '<span class="sr-only"> – ' . e(lt('Startseite')) . '</span></a>';
    };
    $cta = fluid_header_cta();
    $notice = !empty(setting('notice_active')) && '' !== trim((string) setting('notice_text', '')) ? trim((string) setting('notice_text')) : '';
    $topbar = (string) $design->get('topbar');
    $address = fluid_address_lines();
    $social = fluid_social();
    $legal = fluid_legal_links();
    $tagline = trim((string) setting('tagline', ''));
    $ftrText = trim((string) setting('footer_text', ''));
    $statement = trim((string) setting('footer_statement', '')) ?: fluid_name(true);
    $socialList = static function () use ($social): string {
        $h = '';
        foreach ($social as $so) {
            $h .= '<li><a href="' . e($so['url']) . '" target="_blank" rel="noopener me">' . e($so['label']) . '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span></a></li>';
        }

        return $h;
    };
    $pageList = static function () use ($menu): string {
        $h = '';
        foreach ($menu as $m) {
            $h .= '<li><a href="' . e($m['href']) . '"' . ($m['current'] ? ' aria-current="page"' : '') . '>' . e($m['label']) . '</a></li>';
        }

        return $h;
    };
?><!doctype html>
<html lang="<?= e(Language::getCurrent()->code) ?>" class="no-js <?= e($designClasses . $extra) ?>" data-fluid-classes="<?= e($designClasses) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="color-scheme" content="light dark">
<?= PageSettings::head($article, $name) ?>
<?php foreach ($css as $href): ?><link rel="stylesheet" href="<?= e($href) ?>"<?= str_contains($href, '/opt-') ? ' data-fluid-opt' : '' ?>>
<?php endforeach; ?>
<?= $design->head() ?>
<?php if (!Editing::active()): ?><script src="<?= e($kit->assetUrl('js/site.js')) ?>" defer></script>
<?php foreach ($kit->blockJs() as $js): ?><script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
<?php endif; ?>
</head>
<body class="<?= $isHome ? 'is-home' : 'is-sub' ?>">
<a class="skip" id="top" href="#main"><?= e(lt('Zum Inhalt springen')) ?></a>
<div class="page">
<?php if ('' !== $notice): ?><div class="topnote" role="note"><div class="wrap"><?= fluid_title($notice) ?></div></div><?php endif; ?>
<header class="hdr hdr--<?= e($header) ?> fit-60" data-header>
  <?php if ('' !== $topbar && 'off' !== $topbar): $mText = trim((string) setting('topbar_text', '')); $mTel = fluid_phone_href(); ?>
  <div class="hdr__meta"><div class="hdr__meta-in">
    <?php if ('' !== $mText): ?><p class="hdr__meta-text"><?= fluid_title($mText) ?></p><?php endif; ?>
    <ul class="hdr__meta-list" role="list">
      <?php if ($mTel && '' !== fluid_phone()): ?><li><a href="<?= e($mTel) ?>"><?= icon('phone') ?><span><?= e(fluid_phone()) ?></span></a></li><?php endif; ?>
      <?php if ('' !== fluid_email()): ?><li class="hdr__meta-mail"><a href="mailto:<?= e(fluid_email()) ?>"><?= icon('envelope-simple') ?><span><?= e(fluid_email()) ?></span></a></li><?php endif; ?>
      <?php foreach ($social as $so): ?><li class="hdr__meta-social"><a href="<?= e($so['url']) ?>" target="_blank" rel="noopener me"><?= e($so['label']) ?></a></li><?php endforeach; ?>
    </ul>
  </div></div>
  <?php endif; ?>
  <div class="hdr__bar">
    <?= $brand('hdr__brand') ?>
    <?php if ($menu): ?>
    <div class="hdr__nav"><nav class="hnav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><ul class="hnav__list" role="list"><?= implode('', array_map($navItem, $menu)) ?></ul></nav></div>
    <?php endif; ?>
    <div class="hdr__tools">
      <?php if (\count($langs) > 1): ?>
      <ul class="langswitch" role="list" aria-label="<?= e(lt('Sprache')) ?>"><?php foreach ($langs as $l): ?><li><a href="<?= e($l['url']) ?>" hreflang="<?= e($l['code']) ?>" lang="<?= e($l['code']) ?>" title="<?= e($l['name']) ?>"<?= $l['current'] ? ' aria-current="true"' : '' ?>><?= e(strtoupper($l['code'])) ?></a></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ('' !== ($searchPage = trim((string) setting('search_page', '')))): ?><a class="hdr__search" href="<?= e(link_href($searchPage)) ?>" title="<?= e(lt('Suche')) ?>"><?= icon('magnifying-glass') ?><span class="sr-only"><?= e(lt('Suche')) ?></span></a><?php endif; ?>
      <?php if ($cta): ?><a class="btn btn--primary hdr__cta" <?= fluid_link_attrs($cta['link']) ?>><?= e($cta['label']) ?></a><?php endif; ?>
      <?php if ($menu): ?>
      <button type="button" class="menu-btn" popovertarget="mnav" aria-controls="mnav" aria-haspopup="dialog">
        <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e(lt('Menü')) ?></span>
      </button>
      <?php endif; ?>
    </div>
  </div>
</header>
<div class="page__main">
<main id="main" tabindex="-1">
<?= $isPost ? fluid_post_header($article) : '' ?>
<?= $main ?>
<?= $isPost ? fluid_post_footer($article) : '' ?>
</main>
<footer class="ftr ftr--<?= e($footer) ?>">
  <div class="wrap">
  <?php if ('statement' === $footer): ?>
    <div class="ftr__statement">
      <p class="ftr__big"><?= fluid_title($statement) ?></p>
      <?php if ($cta): ?><a class="btn btn--primary ftr__cta" <?= fluid_link_attrs($cta['link']) ?>><?= e($cta['label']) ?><?= icon('arrow-right') ?></a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ('simple' === $footer || 'centered' === $footer): ?>
    <div class="ftr__row">
      <?= $brand('brand--footer') ?>
      <?php if ('' !== $tagline): ?><p class="ftr__tagline"><?= fluid_title($tagline) ?></p><?php endif; ?>
      <?php if ($menu): ?><nav aria-label="<?= e(lt('Seiten')) ?>" class="ftr__inline"><ul class="cluster" role="list"><?= $pageList() ?></ul></nav><?php endif; ?>
    </div>
    <?php if ('' !== $ftrText): ?><p class="ftr__text"><?= nl2br(fluid_title($ftrText), false) ?></p><?php endif; ?>
  <?php else: ?>
    <div class="ftr__grid">
      <div class="ftr__about">
        <?= $brand('brand--footer') ?>
        <?php if ('' !== $tagline): ?><p class="ftr__tagline"><?= fluid_title($tagline) ?></p><?php endif; ?>
        <?php if ('' !== $ftrText): ?><p class="ftr__text"><?= nl2br(fluid_title($ftrText), false) ?></p><?php endif; ?>
      </div>
      <?php if ($address || '' !== fluid_phone() || '' !== fluid_email()): ?>
      <div class="ftr__col">
        <h2 class="ftr__h"><?= e(lt('Kontakt')) ?></h2>
        <?php if ($address): ?><address class="ftr__address"><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
        <?= fluid_quick_contact('ftr__list') ?>
      </div>
      <?php endif; ?>
      <?php if ($menu): ?>
      <nav class="ftr__col" aria-labelledby="ftr-pages">
        <h2 class="ftr__h" id="ftr-pages"><?= e(lt('Seiten')) ?></h2>
        <ul class="ftr__list" role="list"><?= $pageList() ?></ul>
      </nav>
      <?php endif; ?>
      <?php if ($social): ?>
      <div class="ftr__col">
        <h2 class="ftr__h"><?= e(lt('Folgen Sie uns')) ?></h2>
        <ul class="ftr__list" role="list"><?= $socialList() ?></ul>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
    <div class="ftr__bottom">
      <p class="ftr__copy">© <?= e(date('Y')) ?> <?= e($name) ?></p>
      <?php if ($legal): ?><nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul class="cluster ftr__legal" role="list"><?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
      <?php if (('simple' === $footer || 'centered' === $footer) && $social): ?><ul class="cluster ftr__legal" role="list" aria-label="<?= e(lt('Social Media')) ?>"><?= $socialList() ?></ul><?php endif; ?>
    </div>
  </div>
</footer>
</div>
</div>
<?php if ($menu): ?>
<div class="mnav" id="mnav" popover role="dialog" aria-labelledby="mnav-title" data-mnav>
  <div class="mnav__head">
    <p class="mnav__title" id="mnav-title"><?= e(lt('Menü')) ?></p>
    <button type="button" class="mnav__close" popovertarget="mnav" popovertargetaction="hide" autofocus><?= icon('x') ?><span class="sr-only"><?= e(lt('Menü schließen')) ?></span></button>
  </div>
  <nav class="mnav__nav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><ul class="mnav__list mnav__list--1" role="list">
    <?php foreach ($menu as $m): ?><li><a class="mnav__link" href="<?= e($m['href']) ?>"<?= $m['current'] ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?>
  </ul></nav>
  <?php if (\count($langs) > 1): ?><ul class="langswitch langswitch--wide" role="list" aria-label="<?= e(lt('Sprache')) ?>"><?php foreach ($langs as $l): ?><li><a href="<?= e($l['url']) ?>" hreflang="<?= e($l['code']) ?>" lang="<?= e($l['code']) ?>"<?= $l['current'] ? ' aria-current="true"' : '' ?>><?= e($l['name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>
<link rel="stylesheet" href="<?= e($kit->assetUrl('css/overlay.css')) ?>">
</body>
</html>
<?php };
