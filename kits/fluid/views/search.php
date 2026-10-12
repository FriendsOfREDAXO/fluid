<?php
/**
 * Suche (FriendsOfRedaxo\Fluid\Search\Search, Loupe): Suchfeld und Treffer mit Textausschnitt. Ohne JavaScript (GET ?q=).
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
use FriendsOfRedaxo\Fluid\Search\Search;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;

$q = trim(mb_substr(Request::get('q', 'string', ''), 0, 100));
$res = '' !== $q ? Search::search($q, Language::getCurrentId(), (int) ($d['limit'] ?? 20)) : null;
$id = $b->domId() . '-q';
?>
<div class="wrap fsearch">
  <?= fluid_head($b, '', null === $b->prev ? 'h1' : 'h2') /* erster Block der Seite: Hauptüberschrift */ ?>
  <form class="fsearch__form" role="search" method="get" action="#<?= e($b->domId()) ?>">
    <label class="sr-only" for="<?= e($id) ?>"><?= e(lt('Suchbegriff')) ?></label>
    <input class="fsearch__input" id="<?= e($id) ?>" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(trim((string) ($d['placeholder'] ?? '')) ?: lt('Wonach suchen Sie?')) ?>" maxlength="100" autocomplete="off" enterkeyhint="search"<?= '' === $q && !is_editing() ? ' autofocus' : '' ?>>
    <button class="btn btn--primary" type="submit"><?= icon('magnifying-glass') ?><span><?= e(lt('Suchen')) ?></span></button>
  </form>
  <?php if (null !== $res): ?>
  <p class="fsearch__count" role="status"><?= e(0 === $res['total'] ? lt('Keine Treffer für „{q}“.', ['q' => $q]) : (1 === $res['total'] ? lt('1 Treffer') : lt('{n} Treffer', ['n' => $res['total']]))) ?></p>
  <?php if ($res['hits']): ?>
  <ol class="fsearch__list" role="list">
    <?php foreach ($res['hits'] as $h): ?>
    <li class="fsearch__hit"><a class="fsearch__title" href="<?= e($h['url']) ?>"><?= $h['title'] ?></a><?php if ('' !== $h['excerpt']): ?><p class="fsearch__excerpt"><?= $h['excerpt'] ?></p><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>
  <?php elseif ('' !== trim((string) ($d['empty_text'] ?? ''))): ?><p class="fsearch__empty"><?= e($d['empty_text']) ?></p><?php endif; ?>
  <?php endif; ?>
</div>
