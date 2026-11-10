<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Security\User;
use Throwable;

use function sprintf;
use function usort;

/**
 * Bearbeitungssperre (Website → Einstellungen → Redaktion, Standard aus; braucht die Präsenz) – aus KLXM Studio
 * (Core\EditLock, MIT).
 *
 * Gesperrt hält, wer eine Seite in einer Sprache am längsten ohne Unterbrechung im Bearbeiten-Modus hat – abgeleitet aus
 * der Präsenz (Herzschlag etwa alle 15 s, frei spätestens Presence::TTL Sekunden nach dem Schließen). Keine eigene
 * Tabelle, keine hängenden Sperren. Wer gesperrt ist, sieht die Seite nur an (auch mit ?fluid_edit=1); Speichern über den
 * Editor, die Seitenleiste und MCP lehnt fluid mit HTTP 423 ab. Administratoren übernehmen mit take(): Ihr Eintrag rückt
 * vor den bisherigen Inhaber, der es beim nächsten Herzschlag erfährt. Gäste (Gastzugang) und Abläufe ohne Person
 * (Konsole, Cronjob) sperrt nichts. Das Backend des Cores kennt die Sperre nicht.
 */
final class EditLock
{
    public const string TYPE = 'article';

    /** Selbsttest: an/aus unabhängig von der Einstellung */
    private static ?bool $force = null;

    public static function on(): bool
    {
        if (null !== self::$force) {
            return self::$force && PresenceApi::on();
        }
        try {
            return (bool) Settings::get('edit_lock', false) && PresenceApi::on();
        } catch (Throwable) {
            return false;
        }
    }

    /** Schlüssel der Präsenz für eine Seite in einer Sprache („Sprache:Artikel“) */
    public static function id(Article $article): string
    {
        return $article->languageId . ':' . $article->id;
    }

    /**
     * Inhaber der Sperre: aktiver Eintrag der Präsenz mit dem frühesten Beginn – oder null.
     *
     * @return array<string, mixed>|null
     */
    public static function holder(Article $article): ?array
    {
        if (!self::on()) {
            return null;
        }
        $list = Presence::list(['site' => PresenceApi::SITE, 'type' => self::TYPE, 'id' => self::id($article)]);
        usort($list, static fn (array $a, array $b): int => [(int) $a['since'], (string) $a['user']] <=> [(int) $b['since'], (string) $b['user']]);

        return $list[0] ?? null;
    }

    /**
     * Sperrt jemand anderes die Seite für diese Person? Eintrag des Inhabers, sonst null.
     *
     * @param int|false|null $userId ID der Person; false = MCP-Schlüssel ohne Person (gilt immer als fremd), null = wer
     *     gerade angemeldet ist
     * @return array<string, mixed>|null
     */
    public static function blockedFor(Article $article, int|false|null $userId = null): ?array
    {
        if (null === $userId) {
            if (Editing::isGuest()) {
                return null;   // Gäste speichern nie dauerhaft
            }
            $userId = Editing::user()?->id;
            if (null === $userId) {
                return null;   // ohne Person (Konsole, Cronjob)
            }
        }
        $holder = self::holder($article);
        if (null === $holder || (false !== $userId && (string) $holder['user'] === (string) $userId)) {
            return null;
        }

        return $holder;
    }

    /** @param array<string, mixed> $holder */
    public static function message(array $holder): string
    {
        return sprintf('%s bearbeitet diese Seite gerade – Speichern ist gesperrt, bis die Bearbeitung beendet ist.', (string) ($holder['name'] ?? '?'));
    }

    /**
     * Speichern verhindern, wenn jemand anderes die Seite sperrt (HTTP 423).
     *
     * @param int|false|null $userId siehe blockedFor()
     */
    public static function assert(Article $article, int|false|null $userId = null): void
    {
        $holder = self::blockedFor($article, $userId);
        if (null !== $holder) {
            throw new ApiError(self::message($holder), 423);
        }
    }

    /** Darf die Person eine fremde Sperre übernehmen? (Administration) */
    public static function canTake(?User $user = null): bool
    {
        $user ??= Editing::user();

        return null !== $user && $user->admin && !Editing::isGuest();
    }

    /**
     * Sperre übernehmen: der eigene Eintrag der Präsenz (muss bestehen) rückt vor den bisherigen Inhaber.
     *
     * @param bool $log in der Aktivität vermerken (der Selbsttest nicht)
     */
    public static function take(Article $article, int $userId, bool $log = true): bool
    {
        $holder = self::holder($article);
        if (null === $holder || (string) $holder['user'] === (string) $userId) {
            return false;
        }
        Presence::setSince(['site' => PresenceApi::SITE, 'type' => self::TYPE, 'id' => self::id($article), 'user' => (string) $userId], (int) $holder['since'] - 1);
        if ($log) {
            Activity::log('lock_taken', $article->id, 'von ' . (string) ($holder['name'] ?? '?'));
        }

        return true;
    }

    /**
     * Selbsttest (fluid:selftest): Inhaber, Sperre beim Speichern, MCP ohne Person, Übernehmen, abgeschaltet. Arbeitet mit
     * Einträgen der Präsenz für eine Seite, die es nicht gibt, und räumt sie wieder ab.
     *
     * @return array{ok: int, fails: list<string>}
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = static function (string $label, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) {
                ++$ok;
            } else {
                $fails[] = 'Sperre – ' . $label . ': ' . var_export($got, true) . ' statt ' . var_export($want, true);
            }
        };
        $article = Article::getSiteStartArticle();
        if (null === $article) {
            return ['ok' => 0, 'fails' => ['Sperre – keine Startseite für den Test']];
        }
        // Test-Personen ohne Konto: IDs, die es nicht gibt
        $a = 990001;
        $b = 990002;
        $who = static fn (int $u): array => ['site' => PresenceApi::SITE, 'type' => self::TYPE, 'id' => self::id($article), 'user' => (string) $u];
        try {
            self::$force = true;
            Presence::beat($who($a), ['name' => 'Anna'], time() - 30);
            Presence::beat($who($b), ['name' => 'Ben']);
            $eq('Inhaber = wer zuerst bearbeitet', (string) (self::holder($article)['user'] ?? ''), (string) $a);
            $eq('für Ben gesperrt', (string) (self::blockedFor($article, $b)['name'] ?? ''), 'Anna');
            $eq('für Anna frei', self::blockedFor($article, $a), null);
            $eq('Speichern durch Ben abgelehnt (423)', (static function () use ($article, $b): int {
                try {
                    self::assert($article, $b);

                    return 0;
                } catch (ApiError $e) {
                    return $e->getCode();
                }
            })(), 423);
            $eq('MCP-Schlüssel ohne Person gilt als fremd', null !== self::blockedFor($article, false), true);
            $eq('Übernehmen', self::take($article, $b, false), true);
            $eq('nach Übernahme: Ben hält', (string) (self::holder($article)['user'] ?? ''), (string) $b);
            $eq('nach Übernahme: Anna gesperrt', (string) (self::blockedFor($article, $a)['name'] ?? ''), 'Ben');
            self::$force = false;
            $eq('abgeschaltet: keine Sperre', self::blockedFor($article, $a), null);
        } finally {
            foreach ([$a, $b] as $u) {
                Presence::leave($who($u));
            }
            self::$force = null;
        }

        return ['ok' => $ok, 'fails' => $fails];
    }
}
