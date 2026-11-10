<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Uhrzeit */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class TimePicker extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'time';
    }
}
