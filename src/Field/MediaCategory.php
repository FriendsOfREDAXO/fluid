<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Kategorie des Medienpools */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class MediaCategory extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'mediacategory';
    }
}
