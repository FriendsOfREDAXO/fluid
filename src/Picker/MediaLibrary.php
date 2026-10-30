<?php

namespace FriendsOfRedaxo\Fluid\Picker;

use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MediaPool\MediaPoolPermission;
use Redaxo\Core\Security\User;
use Redaxo\Core\Util\Pager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function in_array;

/**
 * Medienauswahl auf der Website (FrontendPicker): Kategorien und Dateien des Medienpools durchsuchen und hochladen.
 * Es gelten die Rechte des Medienpools; im Backend übernimmt das der Medienpool des Cores (rex.pick).
 */
final class MediaLibrary
{
    public const int PER_PAGE = 48;
    public const array IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    /**
     * Medien einer Kategorie (0 = ohne Kategorie, die oberste Ebene des Medienpools) und alle Kategorien mit Rechten.
     *
     * @param list<string> $types erlaubte Endungen (leer = alle)
     * @return array{categories: list<array{id: int, name: string, parent: int}>, category: int, items: list<array<string, mixed>>, more: bool, upload: bool}
     */
    public static function list(User $user, int $category, string $term, array $types, int $page): array
    {
        $perm = self::perm($user);
        if (0 !== $category && !$perm->hasCategoryPerm($category)) {
            $category = 0;
        }
        $allowed = $perm->hasCategoryPerm($category ?: null);
        // Suche über alle Kategorien nur mit Rechten für alle, sonst in der gewählten
        $everywhere = '' !== $term && $perm->hasAll();

        $filter = $everywhere ? [] : ['category_id' => $category ?: null];
        if ('' !== $term) {
            $filter['term'] = $term;
        }
        if ($types) {
            $filter['types'] = $types;
        }

        $pager = new Pager(self::PER_PAGE);
        $pager->setPage(max(0, $page));
        $items = $allowed || $everywhere ? array_map(self::item(...), MediaHandler::getList($filter, [], $pager)) : [];

        return [
            'categories' => self::categories($perm),
            'category' => $category,
            'items' => $items,
            'more' => [] !== $items && $pager->getCursor() + self::PER_PAGE < $pager->getRowCount(),
            'upload' => $allowed,
        ];
    }

    /**
     * @param list<string> $types erlaubte Endungen (leer = alle, die der Medienpool zulässt)
     * @return array<string, mixed>
     */
    public static function upload(User $user, int $category, string $title, array $types, mixed $file): array
    {
        if (!self::perm($user)->hasCategoryPerm($category ?: null)) {
            throw new PickerError('Keine Rechte für diese Medienkategorie.', 403);
        }
        if (!$file instanceof UploadedFile) {
            throw new PickerError('Keine Datei empfangen.');
        }

        return self::item(MediaHandler::add(['file' => $file, 'category_id' => $category ?: null, 'title' => $title], types: $types));
    }

    /** Vorschaubild; mit dem AddOn image_editor die bearbeitete Fassung */
    public static function thumb(string $file): string
    {
        return class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditor::class)
            ? \FriendsOfRedaxo\ImageEditor\ImageEditor::url($file, 320)
            : MediaManager::getUrl('rex_media_small', $file);
    }

    /** @return array<string, mixed> */
    private static function item(Media $media): array
    {
        $ext = strtolower($media->getExtension());
        $image = in_array($ext, self::IMAGE_TYPES, true);

        return [
            'file' => $media->fileName,
            'title' => $media->title,
            'ext' => $ext,
            'image' => $image,
            'thumb' => $image ? self::thumb($media->fileName) : null,
            'size' => $media->getFormattedSize(),
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    private static function perm(User $user): MediaPoolPermission
    {
        $perm = $user->getComplexPerm('media');
        if (!$perm instanceof MediaPoolPermission || !$perm->hasMediaPerm()) {
            throw new PickerError('Keine Rechte für den Medienpool.', 403);
        }

        return $perm;
    }

    /**
     * Kategorien mit Rechten in der Reihenfolge des Medienpools; `parent`: nächste übergeordnete Kategorie mit Rechten
     * (0 = oberste Ebene), damit die Navigation auch bei Rechten nur für Unterkategorien funktioniert
     *
     * @return list<array{id: int, name: string, parent: int}>
     */
    private static function categories(MediaPoolPermission $perm): array
    {
        $out = [];
        $walk = static function (array $cats, int $parent) use (&$walk, &$out, $perm): void {
            foreach ($cats as $cat) {
                /** @var MediaCategory $cat */
                $ok = $perm->hasCategoryPerm($cat->id);
                if ($ok) {
                    $out[] = ['id' => $cat->id, 'name' => $cat->name, 'parent' => $parent];
                }
                $walk($cat->getChildren(), $ok ? $cat->id : $parent);
            }
        };
        $walk(MediaCategory::getRootCategories(), 0);

        return $out;
    }
}
