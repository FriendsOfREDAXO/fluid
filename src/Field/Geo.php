<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Ort „Breite, Länge“ mit Geokodierung */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Geo extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'geo';
    }
}
