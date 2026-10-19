<?php

namespace FriendsOfRedaxo\Fluid\Kit\Native;

use FriendsOfRedaxo\Fluid\AsKit;
use FriendsOfRedaxo\Fluid\Kit;
use Override;

/**
 * Kit „NativeCSS“: Seitenrahmen und Blöcke auf Basis von NativeCSS (https://github.com/skerbis/nativecss, MIT) –
 * Cascade Layers, Container Queries, light-dark(), native Elemente (dialog, details). Erbt vom Kit fluid: Blöcke ohne
 * eigene View kommen von dort und sehen über die Token-Brücke (assets/css/native.css) wie NativeCSS aus.
 */
#[AsKit('native', 'NativeCSS')]
final class NativeKit extends Kit
{
    #[Override]
    public function path(string $file = ''): string
    {
        return dirname(__DIR__) . ('' !== $file ? '/' . $file : '');
    }
}
