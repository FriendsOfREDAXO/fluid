<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Formular-Klasse aus YForm (#[AsForm]) */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class YFormForm extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'yform_form';
    }
}
