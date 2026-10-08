<?php

namespace FriendsOfRedaxo\Blocks\Kit\Fluid;

use FriendsOfRedaxo\Blocks\Kit;
use Override;

/** Kit „fluid“: breakpointlos, für viele Projektarten (aus KLXM Studio). */
final class FluidKit extends Kit
{
    #[Override]
    public function key(): string
    {
        return 'fluid';
    }

    #[Override]
    public function path(string $file = ''): string
    {
        return dirname(__DIR__) . ('' !== $file ? '/' . $file : '');
    }
}
