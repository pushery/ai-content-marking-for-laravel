<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\MediaLibrary;

use Closure;
use Pushery\AiContentMarking\Images\ImageMarker;
use Pushery\AiContentMarking\Marking;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What the original of one media declares, read once from the copy the media library makes of it for its
 * conversions.
 *
 * Before the conversions of a media, the media library copies its original to a local file and names that
 * file in a ConversionWillStartEvent before each conversion. The first of those events reads the marking
 * from the copy, and the conversions take it from here instead of reading the original from its disk once
 * more for each of them. The original of one media is held at a time, and the next media replaces it, so a
 * worker that converts images for days holds one marking, not one for every image.
 */
final class OriginalMarking
{
    /**
     * The key of the media whose original was read, the copy it was read from, and what it declares.
     *
     * @var array{media: mixed, copy: string, marking: Marking|null}|null
     */
    private ?array $held = null;

    public function __construct(private readonly ImageMarker $marker) {}

    /**
     * Reads what the original of the media declares from its copy at the path, unless that copy was read
     * already.
     */
    public function readFromCopy(Media $media, string $copy): void
    {
        $held = $this->heldFor($media);

        if ($held !== null && $held['copy'] === $copy) {
            return;
        }

        $this->held = ['media' => $media->getKey(), 'copy' => $copy, 'marking' => $this->marker->read($copy)->marking()];
    }

    /**
     * What the original of the media declares: what its copy held, where a copy of it was read, and what
     * the callback reads otherwise.
     *
     * @param  Closure(): (Marking|null)  $otherwise
     */
    public function of(Media $media, Closure $otherwise): ?Marking
    {
        $held = $this->heldFor($media);

        return $held === null ? $otherwise() : $held['marking'];
    }

    /**
     * Whether it holds what the original of the media declares.
     */
    public function holds(Media $media): bool
    {
        return $this->heldFor($media) !== null;
    }

    /**
     * @return array{media: mixed, copy: string, marking: Marking|null}|null
     */
    private function heldFor(Media $media): ?array
    {
        return $this->held !== null && $this->held['media'] === $media->getKey() ? $this->held : null;
    }
}
