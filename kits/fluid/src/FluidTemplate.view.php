<?php

use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidKit;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidTemplate;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Core;
use Redaxo\Core\Language\Language;

return static function (ArticleContent $content): void {
    $kit = FluidKit::instance();
    // Inhalt zuerst: so ist bekannt, welche Blöcke (und damit welches CSS) die Seite braucht
    $main = $content->renderContent();
    $article = $content->article;
    $name = Core::getProject()->instanceName;
    $isHome = $article->id === Article::getSiteStartArticleId();
    $home = Article::getSiteStartArticle()?->getUrl() ?? '/';
    $menu = FluidTemplate::menu();
    $title = $isHome ? $name : $article->name . ' – ' . $name;
    $design = $kit->design();
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
    $brand = static fn (string $class = '') => '<a class="brand' . ('' !== $class ? ' ' . $class : '') . '" href="' . e($isHome ? '#main' : $home) . '">'
        . '<span class="brand__mark" aria-hidden="true">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>'
        . '<span class="brand__name">' . e($name) . '</span><span class="sr-only"> – ' . e(lt('Startseite')) . '</span></a>';
?><!doctype html>
<html lang="<?= e(Language::getCurrent()?->code ?? 'de') ?>" class="no-js <?= e($designClasses . $extra) ?>" data-fluid-classes="<?= e($designClasses) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="color-scheme" content="light dark">
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
<header class="hdr hdr--<?= e($header) ?> fit-60" data-header>
  <div class="hdr__bar">
    <?= $brand('hdr__brand') ?>
    <?php if ($menu): ?>
    <div class="hdr__nav"><nav class="hnav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><ul class="hnav__list" role="list"><?= implode('', array_map($navItem, $menu)) ?></ul></nav></div>
    <?php endif; ?>
    <div class="hdr__tools">
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
<?= $main ?>
</main>
<footer class="ftr ftr--<?= e($footer) ?>">
  <div class="wrap">
  <?php if ('simple' === $footer): ?>
    <div class="ftr__row">
      <?= $brand('brand--footer') ?>
      <?php if ($menu): ?><nav aria-label="<?= e(lt('Seiten')) ?>" class="ftr__inline"><ul class="cluster" role="list"><?php foreach ($menu as $m): ?><li><a href="<?= e($m['href']) ?>"<?= $m['current'] ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="ftr__grid">
      <div class="ftr__about"><?= $brand('brand--footer') ?></div>
      <?php if ($menu): ?>
      <nav class="ftr__col" aria-labelledby="ftr-pages">
        <h2 class="ftr__h" id="ftr-pages"><?= e(lt('Seiten')) ?></h2>
        <ul class="ftr__list" role="list"><?php foreach ($menu as $m): ?><li><a href="<?= e($m['href']) ?>"<?= $m['current'] ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul>
      </nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>
    <div class="ftr__bottom"><p class="ftr__copy">© <?= e(date('Y')) ?> <?= e($name) ?></p></div>
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
</div>
<?php endif; ?>
<link rel="stylesheet" href="<?= e($kit->assetUrl('css/overlay.css')) ?>">
</body>
</html>
<?php };
