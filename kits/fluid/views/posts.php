<?php
/**
 * Beiträge eines Blogs: cards · list · featured (erster Beitrag groß, Rest als Karten). Blättern über ?seite=2,
 * Filter über ?tag=slug. Markup der Karten wie die Datenliste (css/data.css), Zusätze in css/b-posts.css.
 * Im Bearbeiten-Modus: „+ Neuer Beitrag“ (legt eine Seite im Blog an) und Entwürfe/offline-Beiträge sichtbar.
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
use FriendsOfRedaxo\Fluid\Blog\Blog;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;

$blog = (int) ($d['blog'] ?? 0);
if ([] === Blog::all()) {
    if (is_editing()) {
        echo '<div class="wrap"><p class="empty-hint">Noch kein Blog: bei einer Menüseite in den Seiteneinstellungen „Blog / News“ anhaken. Deren Unterseiten sind dann Beiträge.</p></div>';
    }

    return;
}
$layout = in_array($b->variant(), ['cards', 'list', 'featured'], true) ? $b->variant() : 'cards';
$limit = max(1, min(60, (int) ($d['limit'] ?? 9) ?: 9));
$pager = !empty($d['pager']);
$page = $pager ? max(1, Request::get('seite', 'int', 1)) : 1;
$tag = !empty($d['tags']) ? preg_replace('~[^a-z0-9-]~', '', Request::get('tag', 'string', '')) : '';
$res = Blog::posts($blog, ['limit' => $limit, 'offset' => ($page - 1) * $limit, 'tag' => $tag, 'drafts' => is_editing()]);
$here = Url::article($b->slice->articleId, Language::getCurrentId());
$q = static fn (array $p) => $here . ([] !== ($p = array_filter($p)) ? '?' . http_build_query($p) : '') . '#' . $b->domId();
$cols = ['s' => 4, 'm' => 3, 'l' => 2][$d['size'] ?? 'm'] ?? 3;
// erster Block der Seite: Hauptüberschrift (ohne eigene Überschrift = Seitenname)
$top = null === $b->prev;
$pageH1 = $top && trim((string) ($d['title'] ?? '')) === '' ? '<h1 class="h1 dl-head">' . e(\Redaxo\Core\Content\Article::getCurrent()?->name ?? '') . '</h1>' : '';
$hTag = !$top && trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
$posts = $res['posts'];
$first = 'featured' === $layout && 1 === $page && $posts ? array_shift($posts) : null;
$card = static function ($p, string $sizes) use ($hTag, $layout) {
    $img = '' !== $p->image ? img($p->image, $sizes, ['ratio' => '16:10']) : '';
    $off = !$p->article->isOnline() ? ' <span class="post-badge">' . e(lt('offline')) . '</span>' : '';

    return '<li class="dl-item post-card">'
        . ('' !== $img ? '<div class="dl-img ratio-16-10">' . $img . '</div>' : '')
        . '<div class="dl-body"><p class="dl-meta">' . fluid_post_meta($p, false) . $off . '</p>'
        . '<' . $hTag . ' class="dl-name"><a href="' . e($p->url) . '" class="dl-link">' . e($p->article->name) . '</a></' . $hTag . '>'
        . ('' !== $p->excerpt ? '<div class="dl-f dl-clamp dl-clamp-3"><p>' . e($p->excerpt) . '</p></div>' : '')
        . '</div></li>';
};
?>
<div class="wrap dl dl--<?= e('list' === $layout ? 'list' : 'cards') ?> posts posts--<?= e($layout) ?>">
  <?= '' !== $pageH1 ? $pageH1 : fluid_head($b, 'dl-head', $top ? 'h1' : 'h2') ?>
  <?php if (!empty($d['tags']) && ($all = Blog::tags($blog))): ?>
  <ul class="post-tags post-tags--filter" role="list" aria-label="<?= e(lt('Nach Schlagwort filtern')) ?>">
    <li><a href="<?= e($q([])) ?>"<?= '' === $tag ? ' aria-current="true"' : '' ?>><?= e(lt('Alle')) ?></a></li>
    <?php foreach ($all as $slug => [$name, $n]): ?><li><a href="<?= e($q(['tag' => $slug])) ?>"<?= $slug === $tag ? ' aria-current="true"' : '' ?>><?= e($name) ?> <span class="post-tags__n"><?= $n ?></span></a></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if (is_editing()): ?>
  <p class="posts-new"><button type="button" class="btn btn--primary btn--sm" data-fluid-new-post="<?= (int) ($blog ?: array_key_first(Blog::all())) ?>">+ <?= e('Neuer Beitrag') ?></button></p>
  <?php endif; ?>
  <?php if (!$res['posts']): ?>
  <p class="dl-empty"><?= e(trim((string) ($d['empty_text'] ?? '')) ?: lt('Noch keine Beiträge.')) ?></p>
  <?php else: ?>
  <?php if ($first): $img = '' !== $first->image ? img($first->image, '(min-width: 1080px) 700px, 100vw', ['ratio' => '16:9']) : ''; ?>
  <article class="post-lead">
    <?php if ($img !== ''): ?><div class="post-lead__img ratio-16-9"><?= $img ?></div><?php endif; ?>
    <div class="post-lead__body">
      <p class="dl-meta"><?= fluid_post_meta($first) ?></p>
      <<?= $hTag ?> class="h2 post-lead__title"><a href="<?= e($first->url) ?>" class="dl-link"><?= e($first->article->name) ?></a></<?= $hTag ?>>
      <?php if ($first->excerpt !== ''): ?><p class="post-lead__text"><?= e($first->excerpt) ?></p><?php endif; ?>
    </div>
  </article>
  <?php endif; ?>
  <?php if ($posts): ?>
  <ul class="dl-items<?= 'list' !== $layout ? ' dl-cols-' . $cols : '' ?>" role="list">
    <?php foreach ($posts as $p) { echo $card($p, 'list' === $layout ? '(min-width: 1080px) 320px, 40vw' : '(min-width: 1080px) 400px, 100vw'); } ?>
  </ul>
  <?php endif; ?>
  <?php endif; ?>
  <?php $pages = (int) ceil($res['total'] / $limit); if ($pager && $pages > 1): ?>
  <nav class="posts-pager" aria-label="<?= e(lt('Blättern')) ?>">
    <?php if ($page > 1): ?><a href="<?= e($q(['tag' => $tag, 'seite' => $page > 2 ? $page - 1 : null])) ?>" rel="prev">← <?= e(lt('Neuere')) ?></a><?php else: ?><span></span><?php endif; ?>
    <span class="posts-pager__n"><?= e(lt('Seite {n} von {total}', ['n' => $page, 'total' => $pages])) ?></span>
    <?php if ($page < $pages): ?><a href="<?= e($q(['tag' => $tag, 'seite' => $page + 1])) ?>" rel="next"><?= e(lt('Ältere')) ?> →</a><?php else: ?><span></span><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php if (trim((string) ($d['more'] ?? '')) !== ''): ?><p class="posts-more"><a class="btn" <?= fluid_link_attrs((string) $d['more']) ?>><?= e(lt('Alle Beiträge')) ?> →</a></p><?php endif; ?>
</div>
