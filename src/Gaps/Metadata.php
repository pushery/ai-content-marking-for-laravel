<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Gaps;

/**
 * Where an output's marking stands in the metadata of the format the output is delivered in.
 *
 * A mail, a PDF or an HTML file carries metadata. Free-form text, the text of a chat message for
 * instance, carries none, and only a watermark in the text itself can mark it.
 */
enum Metadata: string
{
    /** The format carries metadata, and the marking stands in it under a signature that covers it. */
    case Signed = 'signed';

    /** The format carries metadata, and the marking stands in it without a signature. */
    case Unsigned = 'unsigned';

    /** The format carries metadata, and the marking is not in it. */
    case Missing = 'missing';

    /** The format carries no metadata. */
    case NotCarried = 'not-carried';
}
