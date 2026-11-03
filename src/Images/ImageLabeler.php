<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Images;

use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;
use InvalidArgumentException;
use Pushery\AiContentMarking\Marking;
use Pushery\AiContentMarking\Notice;
use UnexpectedValueException;

/**
 * Draws the label into an image itself, for the places an overlay does not reach: a mail, a download, a
 * file passed on.
 *
 * The label is the smallest form the Code of Practice asks for, the acronym "AI" on a dark backing in
 * the top right corner, drawn as strokes rather than set in a font, so the package ships no font. Its
 * height is a twentieth of the width of the image, and never under 16 pixels. Drawing encodes the image
 * again, which keeps no XMP, so the marking is written into the result afterwards, as ImageMarker
 * writes it. An image whose source type the configured notice does not show gets the marking and no
 * label, as the label component renders none for it.
 */
final readonly class ImageLabeler
{
    /** The share of the width of the image the label is high. */
    public const float SHARE = 0.05;

    /** The least height of the label, in pixels. */
    public const int SMALLEST = 16;

    public function __construct(private ImageMarker $marker, private Notice $notice) {}

    /**
     * Draws the label into the image at the path and writes the marking into it.
     *
     * @throws InvalidArgumentException when there is no file, or no image Imagick can read
     * @throws UnexpectedValueException when the image does not hold the marking once written
     */
    public function stamp(string $path, Marking $marking): void
    {
        $image = is_file($path) ? file_get_contents($path) : false;

        if ($image === false) {
            throw new InvalidArgumentException("There is no file to label at [{$path}].");
        }

        file_put_contents($path, $this->stamped($image, $marking));
    }

    /**
     * The bytes of an image with the label drawn into them and the marking written into them.
     *
     * @throws InvalidArgumentException when the bytes are no image Imagick can read
     * @throws UnexpectedValueException when the image does not hold the marking once written
     */
    public function stamped(string $image, Marking $marking): string
    {
        if (! $this->notice->shows($marking)) {
            return $this->marker->mark($image, $marking);
        }

        try {
            $imagick = new Imagick;
            $imagick->readImageBlob($image);
            $imagick->drawImage($this->drawing($imagick->getImageWidth()));
            $drawn = $imagick->getImageBlob();
        } catch (ImagickException $exception) {
            throw new InvalidArgumentException('The bytes are no image Imagick can read: '.$exception->getMessage(), 0, $exception);
        }

        return $this->marker->mark($drawn, $marking);
    }

    /** The backing and the strokes of the acronym, in the top right corner of an image of the width. */
    private function drawing(int $width): ImagickDraw
    {
        $size = max(self::SMALLEST, (int) round($width * self::SHARE));
        $margin = round($size * 0.3);
        $right = $width - $margin;
        $left = $right - round($size * 1.5);
        $glyph = $size * 0.56;
        $stroke = max(1.0, $glyph * 0.2);
        $baseline = $margin + ($size + $glyph) / 2;
        $capline = $baseline - $glyph;
        $letterA = $glyph * 0.8;
        $start = $left + ($right - $left - $letterA - $glyph * 0.28 - $stroke) / 2;
        $apex = $start + $letterA / 2;
        $bar = $capline + $glyph * 0.62;
        $stem = $start + $letterA + $glyph * 0.28 + $stroke / 2;

        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('rgba(17, 17, 17, 0.82)'));
        $draw->roundRectangle($left, $margin, $right, $margin + $size, $size * 0.2, $size * 0.2);
        $draw->setStrokeColor(new ImagickPixel('#ffffff'));
        $draw->setStrokeWidth($stroke);
        $draw->line($start, $baseline, $apex, $capline);
        $draw->line($apex, $capline, $start + $letterA, $baseline);
        $draw->line($apex - $letterA * 0.31, $bar, $apex + $letterA * 0.31, $bar);
        $draw->line($stem, $capline, $stem, $baseline);

        return $draw;
    }
}
