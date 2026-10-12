<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\Database\Sql;
use Throwable;

use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Domains einer Website (Tabelle rex_fluid_domain, Modell aus KLXM Studio – Core\Domains und Core\Landings):
 *
 *  - primary: Hauptadresse. Canonical, Sitemap und absolute Links nutzen sie. Ohne Eintrag gilt der aufgerufene Host.
 *  - alias:   weitere Domain derselben Website – leitet per 301 auf die Hauptadresse (nie für lokale Hosts).
 *  - landing: eigene Domain für eine Seite (auf Wunsch mit Unterseiten) – „/“ zeigt die Seite. Links auf Seiten der
 *             Landingpage bleiben auf der Landing-Domain, alle anderen zeigen absolut auf die Hauptadresse.
 *             Canonical „own“ = Landing-Domain ist maßgeblich, „mirror“ = Hauptadresse.
 */
final class Domains
{
    public const string TABLE = 'rex_fluid_domain';

    /** @var list<Domain>|null */
    private static ?array $all = null;

    /** @return list<Domain> */
    public static function all(): array
    {
        if (null !== self::$all) {
            return self::$all;
        }
        self::$all = [];
        try {
            foreach (Sql::factory()->getArray('SELECT * FROM ' . self::TABLE . ' ORDER BY id') as $r) {
                $role = DomainRole::tryFrom((string) $r['role']);
                if (!$role || '' === trim((string) $r['host'])) {
                    continue;
                }
                self::$all[] = new Domain((int) $r['id'], self::host((string) $r['host']), $role,
                    $r['article_id'] ? (int) $r['article_id'] : null, (bool) $r['subtree'], 'mirror' !== $r['canonical']);
            }
        } catch (Throwable) {
            // Tabelle noch nicht angelegt
        }

        return self::$all;
    }

    public static function primary(): ?Domain
    {
        foreach (self::all() as $d) {
            if (DomainRole::Primary === $d->role) {
                return $d;
            }
        }

        return null;
    }

    public static function forHost(string $host): ?Domain
    {
        $host = self::host($host);
        foreach (self::all() as $d) {
            if ($d->host === $host) {
                return $d;
            }
        }

        return null;
    }

    /** Landing-Domain, zu der eine Seite gehört (Einstiegsseite oder – mit Unterseiten – darunter) */
    public static function landingFor(int $articleId, array $ancestors): ?Domain
    {
        foreach (self::all() as $d) {
            if (DomainRole::Landing === $d->role && null !== $d->articleId
                && ($d->articleId === $articleId || ($d->subtree && \in_array($d->articleId, $ancestors, true)))) {
                return $d;
            }
        }

        return null;
    }

    /** Host ohne Schema, Pfad und Port, klein */
    public static function host(string $h): string
    {
        $h = strtolower(trim($h));
        $h = (string) preg_replace('~^https?://~', '', $h);

        return trim((string) preg_replace('~[/:].*$~', '', $h), '.');
    }

    /** Lokale Entwicklungsadresse – nie Quelle einer Weiterleitung auf die Hauptadresse */
    public static function isLocal(string $host): bool
    {
        $h = self::host($host);

        return 'localhost' === $h || str_ends_with($h, '.localhost') || str_ends_with($h, '.test') || str_starts_with($h, '127.') || '::1' === $h;
    }

    public static function reset(): void
    {
        self::$all = null;
    }
}
