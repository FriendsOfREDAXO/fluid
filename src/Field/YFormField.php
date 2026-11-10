<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Feld der gewählten YForm-Tabelle */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class YFormField extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'yform_field';
    }
}
