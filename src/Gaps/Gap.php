<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Gaps;

/**
 * A layer of marking an output lacks, measured against Measure 1.1 of the European Commission's Code
 * of Practice on the transparency of content that AI generates: digitally signed metadata where the
 * format carries metadata, and an imperceptible watermark in any text that is not very short.
 */
enum Gap: string
{
    /** The output is long enough to need a watermark, and its model does not watermark text. */
    case WatermarkMissing = 'watermark-missing';

    /** The output is long enough to need a watermark, and whether its model watermarks text is not known. */
    case WatermarkUnknown = 'watermark-unknown';

    /** The output's format carries metadata, and the marking is not in it. */
    case MetadataMissing = 'metadata-missing';

    /** The output's marking stands in metadata that no signature covers. */
    case MetadataUnsigned = 'metadata-unsigned';
}
