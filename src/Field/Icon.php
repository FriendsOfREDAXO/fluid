<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Symbol */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Icon extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'icon';
    }
}
