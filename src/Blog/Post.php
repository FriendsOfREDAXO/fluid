<?php

namespace FriendsOfRedaxo\Fluid\Blog;

use DateTimeImmutable;
use FriendsOfRedaxo\Fluid\PageSettings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Database\Sql;

/**
 * Ein Beitrag (Artikel in einem Blog) mit seinen Angaben – für Übersichten, Feed und Beitragskopf.
 */
final readonly class Post
{
    /** @param list<string> $tags */
    public function __construct(
        public Article $article,
        public DateTimeImmutable $date,
        public string $author,
        public array $tags,
        public string $image,
        public string $excerpt,
        public string $url,
    ) {}

    public static function get(int $id, int $languageId): ?self
    {
        $a = Article::get($id, $languageId);
        if (!$a) {
            return null;
        }
        $s = PageSettings::get($id, $languageId);
        $row = Sql::factory()->getArray('SELECT post_date, author, tags FROM ' . PageSettings::TABLE . ' WHERE article_id = ? AND language_id = ?', [$id, $languageId])[0] ?? [];
        $date = !empty($row['post_date']) ? new DateTimeImmutable((string) $row['post_date']) : new DateTimeImmutable('@' . $a->createDate);

        return new self($a, $date, trim((string) ($row['author'] ?? '')), Blog::splitTags((string) ($row['tags'] ?? '')),
            (string) $s['image'], trim((string) $s['description']), $a->getUrl());
    }
}
