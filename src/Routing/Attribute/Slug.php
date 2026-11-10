<?php

namespace FriendsOfRedaxo\Fluid\Routing\Attribute;

use Attribute;

/** Spalte, aus der die Adresse entsteht */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Slug extends MetaColumn
{
    /** @param bool $withId ID anhängen („titel-12“): stabil, wenn der Titel nicht eindeutig ist */
    public function __construct(string $column, public bool $withId = false, ?string $label = null, bool $editable = true)
    {
        parent::__construct($column, $label, $editable);
    }

    public function role(): string
    {
        return 'slug';
    }

    public function kind(): string
    {
        return 'text';
    }

    public function defaultLabel(): string
    {
        return 'Titel (ergibt die Adresse)';
    }
}
