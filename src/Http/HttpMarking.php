<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Http;

use InvalidArgumentException;
use Pushery\AiContentMarking\Mail\MailMarking;
use Pushery\AiContentMarking\Marking;

/**
 * Writes a marking into the header section of an HTTP response.
 *
 * The response carries the field a mail carries, `Digital-Source-Type`, with the same value: the
 * term's URI in the IPTC vocabulary, and the generator and the moment as parameters. HTTP writes the
 * parameters of a field in the form of RFC 8187, the one RFC 2231 gives a mail, so a generator
 * outside printable ASCII is written the same way in both, and one value serves both.
 *
 * A client that reads the response before its body finds the marking there. A response sends its
 * header section first, a stream before its first byte, so set the field from what is known when the
 * response starts: where a fallback can still change the model, leave the model out. The marking in
 * the body, written by `MarkedText`, stays the record of the content itself.
 */
final class HttpMarking
{
    /** The name of the header field that carries a marking. */
    public const string HEADER = MailMarking::HEADER;

    /**
     * The header field for a marking, by its name, in the shape `response()->json()` and
     * `withHeaders()` take.
     *
     * @return array<string, string>
     *
     * @throws InvalidArgumentException when the generator is not UTF-8 text
     */
    public static function headers(Marking $marking): array
    {
        return [self::HEADER => self::value($marking)];
    }

    /**
     * The value of the header field for a marking.
     *
     * @throws InvalidArgumentException when the generator is not UTF-8 text
     */
    public static function value(Marking $marking): string
    {
        return MailMarking::value($marking);
    }
}
