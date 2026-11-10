<?php

namespace FriendsOfRedaxo\Fluid\Routing;

/**
 * Feld eines Eintrags, das ein Editor anbieten darf (fluid zeigt sie in den Seiteneinstellungen der Detailseite).
 * Gespeichert wird über die Quelle ({@see RouteSource::save()}).
 */
final readonly class EditableField
{
    /**
     * @param string $role slug, title, description, image
     * @param string $kind text, textarea, media
     */
    public function __construct(
        public string $role,
        public string $name,
        public string $label,
        public string $kind,
        public string $value,
    ) {}
}
