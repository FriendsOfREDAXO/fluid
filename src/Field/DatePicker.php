<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Datum */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class DatePicker extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'date';
    }
}
