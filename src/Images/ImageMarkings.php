<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Images;

use Pushery\AiContentMarking\DigitalSourceType;
use Pushery\AiContentMarking\Marking;

/**
 * What an image declares about its origin: the marking its XMP carries, and whether it embeds a
 * C2PA manifest, with the source type that manifest names.
 *
 * Every field holds a value read from the file. A comment, an empty packet or a term outside the
 * IPTC vocabulary declares nothing, so an image that carries only those reads as unmarked.
 */
final readonly class ImageMarkings
{
    /**
     * @param  Marking|null  $xmp  the IPTC Digital Source Type in the image's XMP, with the tool
     *                             and the moment the XMP names where it names them
     * @param  bool  $c2pa  whether the image embeds a C2PA manifest store or points at a manifest;
     *                      its signature is not verified
     * @param  DigitalSourceType|null  $c2paSourceType  the IPTC term the embedded manifest names,
     *                                                  where it names one
     */
    public function __construct(
        public ?Marking $xmp = null,
        public bool $c2pa = false,
        public ?DigitalSourceType $c2paSourceType = null,
    ) {}

    /**
     * Whether the image carries a marking of any kind: a source type in its XMP or a C2PA manifest.
     */
    public function isMarked(): bool
    {
        return $this->xmp instanceof Marking || $this->c2pa;
    }

    /**
     * Whether the image declares that generative AI created or edited it, in its XMP or in its C2PA
     * manifest.
     */
    public function marksGenerativeAi(): bool
    {
        return ($this->xmp?->sourceType->involvesGenerativeAi() ?? false)
            || ($this->c2paSourceType?->involvesGenerativeAi() ?? false);
    }

    /**
     * The marking a copy of the image should carry: the XMP's, or a marking of the source type the
     * C2PA manifest names, since the manifest itself does not survive a conversion.
     */
    public function marking(): ?Marking
    {
        if ($this->xmp instanceof Marking) {
            return $this->xmp;
        }

        return $this->c2paSourceType instanceof DigitalSourceType ? new Marking($this->c2paSourceType) : null;
    }
}
