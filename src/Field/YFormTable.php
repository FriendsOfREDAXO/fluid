<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Tabelle aus YForm */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class YFormTable extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'yform_table';
    }
}
