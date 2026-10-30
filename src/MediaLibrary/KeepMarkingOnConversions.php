<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\MediaLibrary;

use Illuminate\Contracts\Filesystem\Factory;
use InvalidArgumentException;
use Pushery\AiContentMarking\Images\ImageMarker;
use Pushery\AiContentMarking\Marking;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use UnexpectedValueException;

/**
 * Carries the marking of an original onto every conversion the media library derives from it.
 *
 * A conversion is a new file. gd keeps no XMP at all, and no driver keeps a C2PA manifest, so once
 * a conversion is stored, its original is read, and the marking it declares is written into the
 * conversion and read back by value: the original's XMP marking, or a marking of the source type
 * its C2PA manifest names. The manifest itself is not carried over, since a copy of it would vouch
 * for pixels it was never signed for. An original that declares nothing leaves its conversions as
 * they are.
 */
final readonly class KeepMarkingOnConversions
{
    public function __construct(
        private ImageMarker $marker,
        private Factory $disks,
    ) {}

    /**
     * @throws InvalidArgumentException when the conversion is no image the marker can write into
     * @throws UnexpectedValueException when the original is gone, or the conversion does not hold the marking
     */
    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        $media = $event->media;
        $original = $this->disks->disk($media->disk);
        $stream = $original->readStream($media->getPathRelativeToRoot()) ?? throw new UnexpectedValueException("The original of media [{$media->uuid}] is not on its disk any more.");

        try {
            $marking = $this->marker->inspect((string) stream_get_contents($stream, ImageMarker::HEAD_BYTES))->marking();
        } finally {
            fclose($stream);
        }

        if (! $marking instanceof Marking) {
            return;
        }

        $disk = $this->disks->disk($media->conversions_disk ?: $media->disk);
        $path = $media->getPathRelativeToRoot($event->conversion->getName());

        $disk->put($path, $this->marker->mark((string) $disk->get($path), $marking));
    }
}
