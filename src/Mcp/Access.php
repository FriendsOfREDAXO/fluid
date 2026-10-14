<?php

namespace FriendsOfRedaxo\Fluid\Mcp;

use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\Perm;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Security\User;

use function in_array;
use function is_array;

/**
 * Wer fragt – und was darf er? Zwei Arten:
 *
 * - **Schlüssel** (Bearer-Token): handelt als REDAXO-Benutzer, dessen Rechte gelten; scope read/write, ggf. veröffentlichen.
 * - **Öffentlich** (ohne Schlüssel): nur wenn der Admin es einschaltet und nur, was er freigibt – online-Seiten (live),
 *   Suche, Blog, ausgewählte YForm-Tabellen. Nie Entwürfe, nie schreiben.
 */
final class Access
{
    /** Einstellungen (Backend → fluid → MCP), alles standardmäßig aus */
    public const array DEFAULTS = [
        'enabled' => false,
        'public' => false,
        'public_pages' => true,
        'public_search' => true,
        'public_blog' => true,
        'public_tables' => [],
        // YForm-Tabellen für Schlüssel: Tabellenname => read|write (fehlend = kein Zugriff)
        'tables' => [],
        'instructions' => '',
    ];

    private function __construct(
        public readonly ?User $user,
        public readonly bool $write,
        public readonly bool $publish,
        public readonly ?int $tokenId,
        public readonly string $label,
    ) {}

    /** @return array<string, mixed> */
    public static function config(): array
    {
        $cfg = FluidAddon::instance()->getConfig('mcp', []);

        return (is_array($cfg) ? $cfg : []) + self::DEFAULTS;
    }

    /** @param array{id: int, name: string, scope: string, publish: bool, user: User} $token */
    public static function forToken(array $token): self
    {
        $write = 'write' === $token['scope'];

        return new self($token['user'], $write, $write && $token['publish'] && Perm::can($token['user'], 'version[live_version]'), $token['id'], $token['name']);
    }

    public static function anonymous(): self
    {
        return new self(null, false, false, null, 'öffentlich');
    }

    public function isPublic(): bool
    {
        return null === $this->user;
    }

    public function describe(): string
    {
        if ($this->isPublic()) {
            return 'öffentlicher Zugang: nur veröffentlichte Inhalte, nur lesen';
        }

        return 'Schlüssel „' . $this->label . '“ für ' . ($this->user->name ?: $this->user->login) . ': '
            . ($this->write ? 'lesen und ändern (Entwurf)' . ($this->publish ? ', veröffentlichen' : ', ohne Veröffentlichen') : 'nur lesen');
    }

    /** Darf diese Seite lesen? Öffentlich: nur online; Schlüssel: Strukturrecht des Benutzers */
    public function canReadPage(Article $a): bool
    {
        if ($this->isPublic()) {
            return (bool) self::config()['public_pages'] && $a->isOnline();
        }

        return $this->canStructure($a);
    }

    public function canWritePage(Article $a): bool
    {
        return $this->write && $this->canStructure($a);
    }

    private function canStructure(Article $a): bool
    {
        if ($this->user->admin) {
            return true;
        }
        $perm = $this->user->getComplexPerm('structure');

        return $perm instanceof StructurePermission && $perm->hasCategoryPerm($a->categoryId);
    }

    public function canModule(string $key): bool
    {
        if (null === $this->user) {
            return false;
        }
        $perm = $this->user->getComplexPerm('modules');

        return $this->user->admin || ($perm instanceof ModulePermission && $perm->hasPerm($key));
    }

    public function can(string $perm): bool
    {
        return null !== $this->user && Perm::can($this->user, $perm);
    }

    /** Zugriff auf eine YForm-Tabelle: 'read', 'write' oder null */
    public function table(string $name): ?string
    {
        $cfg = self::config();
        if ($this->isPublic()) {
            return in_array($name, (array) $cfg['public_tables'], true) ? 'read' : null;
        }
        $mode = ((array) $cfg['tables'])[$name] ?? null;
        if ('write' === $mode) {
            return $this->write ? 'write' : 'read';
        }

        return 'read' === $mode ? 'read' : null;
    }

    /** @return list<string> lesbare Tabellen */
    public function tables(): array
    {
        $cfg = self::config();
        $names = $this->isPublic() ? (array) $cfg['public_tables'] : array_keys((array) $cfg['tables']);

        return array_values(array_filter(array_map('strval', $names), fn (string $n) => null !== $this->table($n)));
    }
}
