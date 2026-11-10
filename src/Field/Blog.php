<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Blog (Menüseite mit „Blog / News“) */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Blog extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'blog';
    }
}
