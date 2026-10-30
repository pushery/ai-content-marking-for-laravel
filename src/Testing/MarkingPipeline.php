<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Testing;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Imagick;
use ImagickPixel;
use Pushery\AiContentMarking\Images\ImageMarker;
use Pushery\AiContentMarking\Marking;
use RuntimeException;
use UnexpectedValueException;

/**
 * Checks, inside an application's own test suite, that its image pipeline keeps a marking.
 *
 * It writes two images, one marked and one not, sends both through every stage of the pipeline in
 * turn and reads both after each stage. The marked image has to come out with the source type and
 * the generator it went in with, and the unmarked one with no marking at all. A reader that finds a
 * marking everywhere and a pipeline that marks every file both look like success from the outside,
 * and the unmarked image is what tells them apart from a pipeline that keeps the marking. A failure
 * names the stage that lost the marking or made one up.
 *
 *     MarkingPipeline::through([
 *         'thumbnail' => fn (string $path): string => $this->thumbnail($path),
 *         'upload' => fn (string $path): string => $this->upload($path),
 *     ])->assertKeepsMarking();
 */
final readonly class MarkingPipeline
{
    /** The generator the marked image declares, so a stage that keeps the term but drops the tool shows. */
    public const string GENERATOR = 'pushery/ai-content-marking-for-laravel';

    private ImageMarker $marker;

    /**
     * @param  array<string, Closure(string): string>  $stages  by name, each taking the path of an
     *                                                          image and returning the path of the
     *                                                          image it made
     */
    private function __construct(private array $stages)
    {
        $this->marker = new ImageMarker;
    }

    /**
     * @param  array<string, Closure(string): string>  $stages  by name, each taking the path of an
     *                                                          image and returning the path of the
     *                                                          image it made
     */
    public static function through(array $stages): self
    {
        return new self($stages);
    }

    /**
     * Sends a marked and an unmarked image through every stage, in an image format Imagick writes.
     *
     * @throws UnexpectedValueException naming the stage that lost the marking, changed it, marked the
     *                                  unmarked image, or made no image
     * @throws RuntimeException when ext-imagick is not loaded, or no application is booted
     */
    public function assertKeepsMarking(string $format = 'jpeg'): void
    {
        $marking = Marking::generated(self::GENERATOR, new DateTimeImmutable('2026-08-02T00:00:00+00:00'));
        $directory = $this->scratchRoot().'/ai-content-marking-'.bin2hex(random_bytes(8));
        mkdir($directory, 0o755, true);

        try {
            $image = $this->image($format);
            $marked = $directory.'/marked.'.$format;
            $unmarked = $directory.'/unmarked.'.$format;

            file_put_contents($marked, $this->marker->mark($image, $marking));
            file_put_contents($unmarked, $image);

            // The images it starts from go through the same check as every stage, which proves the
            // reader tells a marked image from an unmarked one before any stage is judged by it.
            $this->check('the images it started from', $marked, $unmarked, $marking);

            foreach ($this->stages as $stage => $run) {
                $marked = $this->made($stage, $run($marked));
                $unmarked = $this->made($stage, $run($unmarked));

                $this->check("the stage [{$stage}]", $marked, $unmarked, $marking);
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    /**
     * Where the two images are written: `framework/testing` under the storage path of the
     * application whose test runs the check, where Laravel's own test fakes write too. The
     * directory of one check is removed when it ends.
     *
     * @throws RuntimeException when no application is booted
     */
    private function scratchRoot(): string
    {
        $application = Facade::getFacadeApplication();

        return $application instanceof Application
            ? $application->storagePath('framework/testing')
            : throw new RuntimeException('MarkingPipeline writes its images under the storage path of the application whose test runs it, and no application is booted.');
    }

    private function image(string $format): string
    {
        $imagick = extension_loaded('imagick') ? new Imagick : throw new RuntimeException('MarkingPipeline needs ext-imagick: without it no image keeps an XMP marking through a conversion, so a check of that promise would test one that does not hold here.');
        $imagick->newImage(64, 64, new ImagickPixel('#4f46e5'));
        $imagick->setImageFormat($format);

        return $imagick->getImageBlob();
    }

    private function made(string $stage, mixed $path): string
    {
        return is_string($path) && is_file($path) ? $path : throw new UnexpectedValueException("The stage [{$stage}] returned no path of an image it made.");
    }

    private function check(string $where, string $marked, string $unmarked, Marking $expected): void
    {
        $kept = $this->marker->read($marked)->xmp;

        if (! $kept instanceof Marking) {
            throw new UnexpectedValueException("The marking was lost at {$where}: the marked image came out with no marking, where {$expected->sourceType->value} went in.");
        }

        if ($kept->sourceType !== $expected->sourceType || $kept->generator !== $expected->generator) {
            throw new UnexpectedValueException(sprintf(
                'The marking changed at %s: the marked image came out as %s from %s, where %s from %s went in.',
                $where,
                $kept->sourceType->value,
                $kept->generator ?? 'no generator',
                $expected->sourceType->value,
                $expected->generator ?? 'no generator',
            ));
        }

        if ($this->marker->read($unmarked)->isMarked()) {
            throw new UnexpectedValueException("The unmarked image came out of {$where} marked: the pipeline marks images on its own, or the reader finds markings that are not there, and the marked image proves nothing either way.");
        }
    }
}
