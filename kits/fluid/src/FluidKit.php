<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid;

use FriendsOfRedaxo\Fluid\AsKit;
use FriendsOfRedaxo\Fluid\Kit;
use Override;
use Redaxo\Core\Filesystem\Url;

/**
 * Kit „fluid“: breakpointlos, für viele Projektarten (aus KLXM Studio). Grund-Kit, von dem alle anderen erben.
 * Seine Assets liegen im AddOn unter assets/kits/fluid/ und werden mit dem AddOn veröffentlicht.
 */
#[AsKit('fluid', 'Fluid')]
final class FluidKit extends Kit
{
    #[Override]
    public function key(): string
    {
        return self::DEFAULT;
    }

    #[Override]
    public function path(string $file = ''): string
    {
        return dirname(__DIR__) . ('' !== $file ? '/' . $file : '');
    }

    #[Override]
    protected function sourceAsset(string $file): string
    {
        return dirname(__DIR__, 3) . '/assets/kits/fluid/' . $file;
    }

    #[Override]
    protected function publicAssetUrl(string $file): string
    {
        return Url::addonAssets('fluid', 'kits/fluid/' . $file);
    }

    #[Override]
    protected function publish(): void
    {
        // veröffentlicht der Core mit den Assets des AddOns
    }
}
