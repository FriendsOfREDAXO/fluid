<?php

namespace FriendsOfRedaxo\Fluid\Backend;

use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\BlockOps;
use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\Layout;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;

use function in_array;
use function is_array;
use function json_decode;

/**
 * Blöcke in der Slice-Liste des Backends verdoppeln, kopieren, ausschneiden und einfügen – mit BlockOps wie auf der
 * Website und derselben Zwischenablage im Browser (localStorage „fluid-clipboard“, assets/slice-grid.js).
 *
 *  - fluid_op=copy       JSON {clip, label} für die Zwischenablage
 *  - fluid_op=duplicate  Kopie direkt hinter dem Block (in Spalten: in derselben Spalte)
 *  - fluid_op=paste      Inhalt der Zwischenablage (POST clip) hinter dem Block; mit POST cut wird das Original danach
 *                        gelöscht (Ausschneiden = erst einfügen, dann entfernen – es geht nichts verloren)
 */
#[AsApiFunction('fluid_block_ops')]
final class BlockOpsApi extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        $article = Article::get(Request::request('article_id', 'int'), Request::request('language', 'int'));
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Seite nicht gefunden.');
        }
        $perm = $user->getComplexPerm('structure');
        if (!$user->admin && (!$user->getComplexPerm('language')->hasPerm($article->languageId)
            || !$perm instanceof StructurePermission || !$perm->hasCategoryPerm($article->categoryId))) {
            throw new ApiFunctionException('Keine Berechtigung für diese Seite.');
        }
        $sliceId = Request::request('slice_id', 'int');
        $slice = ArticleSlice::getArticleSliceById($sliceId, $article->languageId, FluidAddon::revision());
        if (null === $slice || $slice->articleId !== $article->id || !\Redaxo\Core\Content\Module::exists($slice->moduleKey)) {
            throw new ApiFunctionException('Block nicht gefunden.');
        }
        $op = Request::request('fluid_op', 'string');

        try {
            if ('copy' === $op) {
                Response::cleanOutputBuffers();
                Response::sendJson(BlockOps::payload($article, $slice->id));
                exit;
            }
            if ('duplicate' === $op) {
                BlockOps::insertAfter($article, $user, $slice->id, BlockOps::payload($article, $slice->id)['clip']);

                return new Result(true, 'Block verdoppelt.');
            }
            if ('paste' === $op) {
                $clip = json_decode(Request::post('clip', 'string', ''), true);
                if (!is_array($clip)) {
                    throw new ApiError('Die Zwischenablage ist leer.');
                }
                $cut = json_decode(Request::post('cut', 'string', ''), true);
                $cut = is_array($cut) ? $cut : [];
                // Ausschneiden: nicht hinter sich selbst bzw. in die eigenen Spalten
                $source = (int) ($cut['slice'] ?? 0);
                if ($source > 0 && in_array($source, [$slice->id, Layout::parentOf($slice)?->id], true)) {
                    throw new ApiError('Ein Block lässt sich nicht in sich selbst verschieben.');
                }
                $new = BlockOps::insertAfter($article, $user, $slice->id, $clip);
                if ($source > 0) {
                    BlockOps::finishCut($user, $cut, $new);

                    return new Result(true, 'Block verschoben.');
                }

                return new Result(true, 'Block eingefügt.');
            }
        } catch (ApiError $e) {
            throw new ApiFunctionException($e->getMessage());
        }

        throw new ApiFunctionException('Unbekannte Aktion.');
    }

    /** Adresse für Aufrufe aus der Slice-Liste (mit CSRF-Token) */
    public static function url(Article $article, int $contentSectionId): string
    {
        return \Redaxo\Core\Filesystem\Url::backendPage('content/edit', [
            'article_id' => $article->id, 'language' => $article->languageId, 'content_section_id' => $contentSectionId,
        ] + self::getUrlParams());
    }
}
