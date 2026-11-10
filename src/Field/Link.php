<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Link: Seite, #anker, https://…, mailto: (Linkauswahl von fluid) */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Link extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'link';
    }
}
