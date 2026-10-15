<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\User;

/**
 * Zustand „Bearbeiten auf der Website“ für die laufende Anfrage.
 *
 * - `user()`: angemeldeter Backend-Benutzer mit Recht auf den aktuellen Artikel (sonst null) – nur dann gibt es die Leiste.
 * - `active()`: Bearbeiten-Modus (`?fluid_edit=1`) – Blöcke geben dann `data-edit`-Attribute aus und die Seite wird
 *   live aus der Datenbank gerendert (Arbeitsversion), nicht aus dem Cache.
 *
 * Außerhalb des Frontends (Backend, Cache-Erzeugung, Konsole) ist beides immer aus, damit der Cache nie Bearbeiten-Markup enthält.
 */
final class Editing
{
    public const string PARAM = 'fluid_edit';

    private static ?User $user = null;
    private static bool $resolved = false;
    private static bool $rendering = false;
    private static bool $guest = false;
    private static ?int $guestRevision = null;

    public static function user(): ?User
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        if (!Core::isFrontend()) {
            return null;
        }
        $user = BackendLogin::createUser();
        // Gastzugang (FLUID_GUEST_USER, z. B. AddOn fluid_demo): Besucher ohne Anmeldung bekommen die Rechte eines Benutzers.
        // Nie mit Admin-Rechten. Schreibende Aktionen von Gästen führt fluid nur aus, wenn ein AddOn sie über FLUID_API_ACTION
        // übernimmt (z. B. in einer Transaktion, die zurückgerollt wird) – sonst lehnt Api sie ab.
        if (!$user) {
            $guest = \Redaxo\Core\ExtensionPoint\Extension::dispatch(new \Redaxo\Core\ExtensionPoint\ExtensionPoint('FLUID_GUEST_USER', null));
            if ($guest instanceof User && !$guest->admin) {
                $user = $guest;
                self::$guest = true;
            }
        }
        if (!$user) {
            return null;
        }
        $article = Article::getCurrent();
        $perm = $user->getComplexPerm('structure');
        if ($article && !($perm instanceof StructurePermission && $perm->hasCategoryPerm($article->categoryId))) {
            return null;
        }

        return self::$user = $user;
    }

    /** Gastzugang: die Person ist nicht angemeldet, sondern arbeitet über FLUID_GUEST_USER (z. B. Demo) */
    public static function isGuest(): bool
    {
        self::user();

        return self::$guest;
    }

    /**
     * Revision, in der Gäste arbeiten (z. B. live, wenn ein AddOn alle Änderungen in einer Transaktion zurückrollt).
     * Ohne Angabe wie angemeldete Benutzer (FluidAddon::revision()).
     */
    public static function guestRevision(?int $revision): void
    {
        self::$guestRevision = $revision;
    }

    /** @internal FluidAddon::revision() */
    public static function revisionForGuest(): ?int
    {
        return self::isGuest() ? self::$guestRevision : null;
    }

    /** Bearbeiten-Modus angefordert und erlaubt */
    public static function requested(): bool
    {
        return 1 === Request::get(self::PARAM, 'int', 0) && null !== self::user();
    }

    /** Gerade wird Bearbeiten-Markup ausgegeben (Seite im Bearbeiten-Modus oder Vorschau eines Blocks) */
    public static function active(): bool
    {
        return self::$rendering;
    }

    /** Bearbeiten-Markup für die Dauer von $fn ausschalten (Blöcke aus Daten, die nicht auf der Seite bearbeitet werden) */
    public static function without(callable $fn): mixed
    {
        $old = self::$rendering;
        self::$rendering = false;
        try {
            return $fn();
        } finally {
            self::$rendering = $old;
        }
    }

    /** @internal Bearbeiten-Markup für die Dauer von $fn einschalten (Vorschau einzelner Blöcke) */
    public static function render(callable $fn): mixed
    {
        $old = self::$rendering;
        self::$rendering = true;
        try {
            return $fn();
        } finally {
            self::$rendering = $old;
        }
    }

    /** @internal MCP-Server: Aktionen im Namen des Benutzers eines Zugangsschlüssels (Protokoll, Versionen) */
    public static function actAs(?User $user): void
    {
        self::$user = $user;
        self::$resolved = true;
    }

    /** @internal vom Addon beim Start im Bearbeiten-Modus gesetzt */
    public static function start(): void
    {
        self::$rendering = true;
    }
}
