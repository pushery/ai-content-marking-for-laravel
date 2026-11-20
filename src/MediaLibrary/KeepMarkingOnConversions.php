<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\MediaLibrary;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Filesystem\Factory;
use InvalidArgumentException;
use Pushery\AiContentMarking\Images\ImageMarker;
use Pushery\AiContentMarking\Marking;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\Conversions\Events\ConversionWillStartEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\ResponsiveImages\Events\ResponsiveImagesGeneratedEvent;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImage;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use UnexpectedValueException;

/**
 * Carries the marking of an original onto every image the media library derives from it: each
 * conversion, and the responsive images it makes of the original and of a conversion for a srcset.
 *
 * A derived image is a new file. gd keeps no XMP at all, and no driver keeps a C2PA manifest, so
 * once the media library has stored one, the marking its original declares is written into the copy
 * and read back by value: the original's XMP marking, or a marking of the source type its C2PA
 * manifest names. The manifest itself is not carried over, since a copy of it would vouch for pixels
 * it was never signed for. A conversion is marked when it is stored, with the responsive images the
 * media library made of it before, and the responsive images of the original when the media library
 * reports them made. An original that declares nothing leaves its copies as they are.
 *
 * The original is read once for all the conversions of a media, from the local copy the media library
 * makes of it before them, and OriginalMarking holds what it declares. The disk of the original is
 * read only where no such copy was read: for responsive images made on their own, and for a PDF or a
 * video, whose conversions start from an image the media library rendered of it.
 *
 * A conversion that cannot hold the marking, such as one in a format that keeps no XMP, does not
 * stay behind as an unmarked copy: it is deleted with its responsive images, the media library
 * records it as not generated, and the refusal goes to the application's exception handler. The
 * media library then goes on with the conversions after it. Responsive images of the original that
 * cannot hold the marking are deleted the same way.
 */
final readonly class KeepMarkingOnConversions
{
    public function __construct(
        private ImageMarker $marker,
        private Factory $disks,
        private ExceptionHandler $exceptions,
        private OriginalMarking $originals,
    ) {}

    /**
     * @throws UnexpectedValueException when the original is not on its disk any more
     */
    public function handle(ConversionWillStartEvent|ConversionHasBeenCompletedEvent|ResponsiveImagesGeneratedEvent $event): void
    {
        $media = $event->media;

        if ($event instanceof ConversionWillStartEvent) {
            $this->readTheCopy($event);

            return;
        }

        $marking = $this->originals->of($media, fn (): ?Marking => $this->markingOfTheOriginal($media));

        if (! $marking instanceof Marking) {
            return;
        }

        // A conversion brings along the responsive images made of it; those of the original come with an event of
        // their own, under no conversion name.
        $conversion = $event instanceof ConversionHasBeenCompletedEvent ? $event->conversion->getName() : '';
        $disk = $this->disks->disk($media->conversions_disk ?: $media->disk);
        $paths = $this->responsiveImages($media, $conversion);

        if ($conversion !== '') {
            array_unshift($paths, $media->getPathRelativeToRoot($conversion));
        }

        try {
            foreach ($paths as $path) {
                $disk->put($path, $this->marker->mark((string) $disk->get($path), $marking));
            }
        } catch (InvalidArgumentException|UnexpectedValueException $refusal) {
            // A disk that does not throw answers a refused delete with false, Laravel's own disks among them. The
            // answer is read, so an image that cannot hold the marking is never reported as gone while it stays.
            $kept = $disk->delete($paths) ? [] : array_values(array_filter($paths, $disk->exists(...)));
            $this->forget($media, $conversion);

            $subject = $conversion === '' ? 'The responsive images' : "The conversion [{$conversion}]";
            $outcome = match (true) {
                $kept !== [] => 'deleting failed, and these files stay unmarked on the disk: '.implode(', ', $kept),
                $conversion === '' => 'they were deleted',
                default => 'it was deleted with its responsive images and is recorded as not generated',
            };

            $this->exceptions->report(new UnexpectedValueException("{$subject} of media [{$media->uuid}] cannot hold the marking of the original, so {$outcome}.", 0, $refusal));
        }
    }

    /**
     * Reads what the original declares from the copy a conversion starts from, where that copy is the
     * original itself. A PDF or a video reaches its conversions as an image the media library rendered
     * of it, in another format, and that image declares nothing of the original's.
     */
    private function readTheCopy(ConversionWillStartEvent $event): void
    {
        if (strcasecmp(pathinfo($event->copiedOriginalFile, PATHINFO_EXTENSION), $event->media->extension) === 0) {
            $this->originals->readFromCopy($event->media, $event->copiedOriginalFile);
        }
    }

    /**
     * What the original declares, read from its disk.
     *
     * @throws UnexpectedValueException when the original is not on its disk any more
     */
    private function markingOfTheOriginal(Media $media): ?Marking
    {
        $stream = $this->disks->disk($media->disk)->readStream($media->getPathRelativeToRoot()) ?? throw new UnexpectedValueException("The original of media [{$media->uuid}] is not on its disk any more.");

        try {
            return $this->marker->inspect((string) stream_get_contents($stream, ImageMarker::HEAD_BYTES))->marking();
        } finally {
            fclose($stream);
        }
    }

    /**
     * The paths of the responsive images the media library lists for a conversion, or for the
     * original under no conversion name.
     *
     * @return array<int, string>
     */
    private function responsiveImages(Media $media, string $conversion): array
    {
        $directory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);

        return $media->responsiveImages($conversion)->files
            ->whereInstanceOf(ResponsiveImage::class)
            ->map(static fn (ResponsiveImage $image): string => $directory.$image->fileName)
            ->values()
            ->all();
    }

    /**
     * Takes a conversion, or the responsive images of the original, off the lists of the media library.
     */
    private function forget(Media $media, string $conversion): void
    {
        $responsiveImages = $media->responsive_images;
        unset($responsiveImages[$conversion === '' ? 'media_library_original' : $conversion]);
        $media->responsive_images = $responsiveImages;

        if ($conversion !== '') {
            $media->markAsConversionNotGenerated($conversion);
        }

        $media->save();
    }
}
