<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Ja/Nein */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Checkbox extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'bool';
    }
}
