<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Mail;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Pushery\AiContentMarking\Marking;

/**
 * Writes a marking into the header section of a mail.
 *
 * The marking travels in one header field, `Digital-Source-Type`. Its value is the term's URI in
 * the IPTC vocabulary, and the generator and the moment follow as parameters, in the form MIME gives
 * the parameters of a header field:
 *
 *     Digital-Source-Type: http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia;
 *      generator="Amazon Bedrock"; generated-at="2026-10-15T07:30:00+00:00"
 *
 * No standard names a header field for this, so the field is the package's own. Its value is a term
 * of the vocabulary the package also writes into HTML and into images, and mail software that does
 * not know the field passes it on unread, as it does with every field it does not know.
 *
 * The value is ASCII throughout. A generator outside printable ASCII, or one holding a quote or a
 * backslash, is written as an RFC 2231 extended parameter in UTF-8, and a long one is split into
 * numbered sections. A line break in a generator therefore never reaches the header section, and
 * `MailMarkingReader` reads back what was written.
 */
final class MailMarking
{
    /** The name of the header field that carries a marking. */
    public const string HEADER = 'Digital-Source-Type';

    /** A parameter value that needs no quotes: a token in the sense of RFC 2045. */
    private const string TOKEN = '/^[\x21\x23-\x27\x2A\x2B\x2D\x2E\x30-\x39\x41-\x5A\x5E-\x7E]+$/';

    /** A parameter value that can stand in quotes as it is: printable ASCII without `"` and `\`. */
    private const string QUOTABLE = '/^[\x20\x21\x23-\x5B\x5D-\x7E]*$/';

    /** The longest stretch of a parameter value that is written in one piece. */
    private const int SECTION_LENGTH = 60;

    /**
     * The header field for a marking, by its name, in the shape Laravel's `Headers` takes as its
     * text headers.
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
        $value = $marking->sourceType->iptcUri();

        if ($marking->generator !== null) {
            $value .= '; '.self::parameter('generator', $marking->generator);
        }

        if ($marking->generatedAt instanceof DateTimeImmutable) {
            $value .= '; generated-at="'.$marking->generatedAt->format(DateTimeInterface::ATOM).'"';
        }

        return $value;
    }

    /**
     * One parameter: bare where its value is a token, in quotes where the value is printable ASCII,
     * and as an RFC 2231 extended parameter otherwise.
     *
     * @throws InvalidArgumentException when the value is not UTF-8 text
     */
    private static function parameter(string $name, string $value): string
    {
        if (strlen($value) <= self::SECTION_LENGTH && preg_match(self::TOKEN, $value) === 1) {
            return $name.'='.$value;
        }

        if (strlen($value) <= self::SECTION_LENGTH && preg_match(self::QUOTABLE, $value) === 1) {
            return $name.'="'.$value.'"';
        }

        if (preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('The generator of a marking written into a mail is UTF-8 text.');
        }

        $sections = self::sections(rawurlencode($value));

        if (count($sections) === 1) {
            return $name."*=utf-8''".$sections[0];
        }

        $parameters = [];

        foreach ($sections as $index => $section) {
            $parameters[] = $name.'*'.$index.'*='.($index === 0 ? "utf-8''" : '').$section;
        }

        return implode('; ', $parameters);
    }

    /**
     * A percent-encoded value in sections of at most `SECTION_LENGTH` characters, each cut outside
     * an escape.
     *
     * @return non-empty-list<string>
     */
    private static function sections(string $encoded): array
    {
        $sections = [];

        while (strlen($encoded) > self::SECTION_LENGTH) {
            $cut = self::SECTION_LENGTH;

            // An escape is three characters, `%` and two hex digits. A cut that would split one
            // moves back to its `%`, so every section decodes on its own.
            if ($encoded[$cut - 1] === '%') {
                $cut--;
            } elseif ($encoded[$cut - 2] === '%') {
                $cut -= 2;
            }

            $sections[] = substr($encoded, 0, $cut);
            $encoded = substr($encoded, $cut);
        }

        $sections[] = $encoded;

        return $sections;
    }
}
