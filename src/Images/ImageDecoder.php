<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Images;

use Closure;
use Imagick;
use ImagickException;
use InvalidArgumentException;

/**
 * Lets Imagick read an image only in one of the formats the package marks, and only up to a number of pixels.
 *
 * ImageMagick picks the decoder for the bytes it is given by looking at them, SVG and MVG among them where they are
 * built in, and sets aside the canvas a header declares before it reads a pixel. So the format is decided here, from
 * the signature the bytes begin with, and Imagick reads them in that format alone. The size comes from the headers
 * first, read without the pixels, and an image whose frames hold more pixels together than the ceiling is refused
 * before a pixel is decoded.
 *
 * ImageMarker and ImageLabeler take one. Those the container makes get the one it binds, with the ceiling of
 * `ai-content-marking.images.max_pixels`.
 */
final readonly class ImageDecoder
{
    /** The most pixels an image may hold, over all its frames, unless the configuration says otherwise: 100 megapixels. */
    public const int DEFAULT_MAX_PIXELS = 100_000_000;

    public function __construct(private int $maxPixels = self::DEFAULT_MAX_PIXELS) {}

    /**
     * What the work makes of the image the bytes hold, read by Imagick in the format their signature names.
     *
     * @param  Closure(Imagick): string  $work
     *
     * @throws InvalidArgumentException when the bytes are in none of the formats, hold more pixels than the ceiling,
     *                                  or are no image Imagick can read
     */
    public function decode(string $image, Closure $work): string
    {
        $format = $this->format($image);

        try {
            $header = new Imagick;
            $header->setFormat($format);
            $header->pingImageBlob($image);
            $this->refuseMorePixelsThanTheCeiling($header);

            $imagick = new Imagick;
            $imagick->setFormat($format);
            $imagick->readImageBlob($image);

            return $work($imagick);
        } catch (ImagickException $exception) {
            throw new InvalidArgumentException('The bytes are no image Imagick can read: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * The name Imagick gives the format whose signature the bytes begin with.
     *
     * @throws InvalidArgumentException when they begin with the signature of none of the formats
     */
    private function format(string $image): string
    {
        return match (true) {
            str_starts_with($image, "\xFF\xD8\xFF") => 'JPEG',
            str_starts_with($image, "\x89PNG\r\n\x1A\n") => 'PNG',
            str_starts_with($image, 'RIFF') && substr($image, 8, 4) === 'WEBP' => 'WEBP',
            str_starts_with($image, "II*\x00") || str_starts_with($image, "MM\x00*") => 'TIFF',
            default => throw new InvalidArgumentException('The bytes are in none of the formats the package reads an image in: JPEG, PNG, WebP and TIFF.'),
        };
    }

    /**
     * Refuses an image whose frames declare more pixels together than the ceiling, read from what Imagick found in
     * their headers.
     *
     * @throws InvalidArgumentException when they do
     */
    private function refuseMorePixelsThanTheCeiling(Imagick $header): void
    {
        $pixels = 0;

        foreach ($header as $frame) {
            $pixels += $frame->getImageWidth() * $frame->getImageHeight();
        }

        if ($pixels > $this->maxPixels) {
            throw new InvalidArgumentException(sprintf(
                'The image is %d pixels, more than the %d the package decodes. Raise ai-content-marking.images.max_pixels to decode it.',
                $pixels,
                $this->maxPixels,
            ));
        }
    }
}
