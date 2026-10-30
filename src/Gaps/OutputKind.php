<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Gaps;

/**
 * What a model did to produce an output.
 *
 * The gap report lists `Translated` and `Corrected` as exceptions instead of assessing them.
 * Article 50(2) of the AI Act does not apply where a system performs an assistive function for
 * standard editing or does not substantially alter the input or its meaning. Which outputs fall
 * there is the application's call, and the kind records the call it made, so the report shows it
 * rather than hiding the output.
 */
enum OutputKind: string
{
    /** A model wrote the text. */
    case Generated = 'generated';

    /** A model rewrote a text a person wrote. */
    case Edited = 'edited';

    /** A model translated a text. */
    case Translated = 'translated';

    /** A model corrected the spelling, grammar or punctuation of a text. */
    case Corrected = 'corrected';

    /** Whether the gap report lists an output of this kind as an exception instead of assessing it. */
    public function isException(): bool
    {
        return $this === self::Translated || $this === self::Corrected;
    }
}
