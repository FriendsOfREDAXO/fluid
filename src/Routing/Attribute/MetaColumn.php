<?php

namespace FriendsOfRedaxo\Fluid\Routing\Attribute;

/**
 * Spalte einer Tabelle mit einer Rolle (Slug, Titel …). Editierbare Spalten kann ein Editor (z. B. fluid auf der Website)
 * anbieten – {@see \FriendsOfRedaxo\Fluid\Routing\RouteSource::editable()}.
 */
abstract readonly class MetaColumn
{
    public function __construct(
        public string $column,
        public ?string $label = null,
        public bool $editable = true,
    ) {}

    /** Rolle: slug, title, description, image, modified */
    abstract public function role(): string;

    abstract public function defaultLabel(): string;

    public function label(): string
    {
        return $this->label ?? $this->defaultLabel();
    }

    /** Art des Eingabefelds: text, textarea, media, none */
    public function kind(): string
    {
        return 'text';
    }
}
