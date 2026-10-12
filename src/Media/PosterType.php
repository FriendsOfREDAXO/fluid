<?php

namespace FriendsOfRedaxo\Fluid\Media;

use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MediaManager\Attribute\AsMediaType;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;
use Redaxo\Core\MediaManager\MediaContext;
use Redaxo\Core\MediaManager\MediaType;
use Redaxo\Core\MediaManager\NegotiatesFormat;
use Redaxo\Core\MediaManager\NegotiatesImageFormats;

/**
 * Media-Manager-Typ „fluid_poster“: Vorschaubild für Videos. Bei einem Video holt ffmpeg ein typisches Standbild,
 * bei einem Bild wird nur verkleinert – so passt der Typ für automatische und gewählte Vorschaubilder.
 * Ausgabe in der Breite höchstens 1600 px, AVIF/WebP je nach Browser; der Media Manager speichert das Ergebnis.
 *
 *     MediaManager::getUrl('fluid_poster', 'film.mp4')
 */
#[AsMediaType('fluid_poster', maxSize: 1600)]
final readonly class PosterType implements MediaType, NegotiatesFormat
{
    use NegotiatesImageFormats;

    public const array VIDEO = ['mp4', 'm4v', 'webm', 'mov', 'ogv'];

    public function __construct(
        private int $maxSize,
    ) {}

    public function process(MediaContext $context): void
    {
        if (\in_array($context->sourceFormat, self::VIDEO, true)) {
            $tmp = Path::addonCache('fluid', 'poster-' . bin2hex(random_bytes(6)) . '.jpg');
            @mkdir(\dirname($tmp), 0775, true);
            try {
                if (null === Ffmpeg::frame($context->sourcePath, $tmp, $this->maxSize)) {
                    throw new MediaNotFoundException('No video frame (ffmpeg missing or failed).');
                }
                $context->decode((string) file_get_contents($tmp));
            } finally {
                @unlink($tmp);
            }
        }
        $context->image->scaleDown($this->maxSize, $this->maxSize);
    }
}
