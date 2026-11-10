<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Formatierter Text (Formatierungsleiste) */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class RichText extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'richtext';
    }
}
