<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Models;

/**
 * Whether a model watermarks the text it writes: embeds a marker in the text itself that the
 * provider's detection can find again.
 *
 * `Unknown` is the answer for a model the register does not list, because a provider that says
 * nothing about watermarking has not said that it watermarks.
 */
enum Watermarking: string
{
    case Yes = 'yes';
    case No = 'no';
    case Unknown = 'unknown';
}
