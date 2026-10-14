<?php

namespace FriendsOfRedaxo\Fluid\Mcp;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\User;

use function is_string;

/**
 * Zugangsschlüssel für den MCP-Server (wie KLXM Studio: Core\Api\Tokens).
 *
 * Format `fluid_<48 Hex>`. Gespeichert wird nur der SHA-256-Hash – den Klartext zeigt die Verwaltung genau einmal.
 * Jeder Schlüssel handelt im Namen eines REDAXO-Benutzers: dessen Rechte (Struktur, Module, Veröffentlichen) gelten.
 *
 * - scope `read`: nur lesen (auch Entwürfe)
 * - scope `write`: lesen und ändern (Änderungen landen im Entwurf)
 * - publish: darf zusätzlich veröffentlichen (nur mit Recht „Veröffentlichen“ des Benutzers)
 */
final class Tokens
{
    public const string TABLE = 'rex_fluid_mcp_token';

    public const array SCOPES = ['read' => 'Nur lesen', 'write' => 'Lesen und ändern'];

    /** @return array{id: int, token: string} */
    public static function create(string $name, string $scope, int $userId, bool $publish, ?string $expires): array
    {
        $token = 'fluid_' . bin2hex(random_bytes(24));
        $sql = Sql::factory()->setTable(self::TABLE)->setValues([
            'name' => mb_substr(trim($name) ?: 'Zugang', 0, 120),
            'token_hash' => hash('sha256', $token),
            'prefix' => substr($token, 0, 12),
            'scope' => isset(self::SCOPES[$scope]) ? $scope : 'read',
            'user_id' => $userId,
            'publish' => $publish && 'write' === $scope ? 1 : 0,
            'expires' => $expires ?: null,
        ])->setRawValue('created', 'NOW()');
        $sql->insert();

        return ['id' => (int) $sql->getLastId(), 'token' => $token];
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Sql::factory()->getArray('SELECT t.*, u.login FROM ' . self::TABLE . ' t LEFT JOIN rex_user u ON u.id = t.user_id ORDER BY t.id DESC');
    }

    public static function revoke(int $id): void
    {
        Sql::factory()->setQuery('DELETE FROM ' . self::TABLE . ' WHERE id = ?', [$id]);
    }

    /**
     * Schlüssel prüfen (Ablauf, Benutzer aktiv) und Nutzung vermerken.
     *
     * @return array{id: int, name: string, scope: string, publish: bool, user: User}|null
     */
    public static function check(string $token): ?array
    {
        if (!str_starts_with($token, 'fluid_') || strlen($token) > 80) {
            return null;
        }
        $row = Sql::factory()->getArray('SELECT * FROM ' . self::TABLE . ' WHERE token_hash = ? LIMIT 1', [hash('sha256', $token)])[0] ?? null;
        if (null === $row || (is_string($row['expires']) && '' !== $row['expires'] && strtotime($row['expires'] . ' 23:59:59') < time())) {
            return null;
        }
        $user = User::get((int) $row['user_id']);
        if (null === $user || 1 !== (int) $user->getValue('status')) {
            return null;
        }
        Sql::factory()->setQuery('UPDATE ' . self::TABLE . ' SET last_used = NOW() WHERE id = ?', [(int) $row['id']]);

        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'scope' => (string) $row['scope'], 'publish' => (bool) $row['publish'], 'user' => $user];
    }

    public static function rememberClient(int $id, string $client): void
    {
        Sql::factory()->setQuery('UPDATE ' . self::TABLE . ' SET client = ? WHERE id = ?', [mb_substr($client, 0, 120), $id]);
    }
}
