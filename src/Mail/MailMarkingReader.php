<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Mail;

use InvalidArgumentException;
use Pushery\AiContentMarking\DigitalSourceType;
use Pushery\AiContentMarking\Marking;

/**
 * Finds the markings in the header section of a mail, and the DKIM signatures that name their field.
 *
 * It reads a mail in its raw form, the text RFC 5322 describes: the header section up to the first
 * empty line, with folded lines joined. A `Digital-Source-Type` field whose value is a term of the
 * IPTC vocabulary is a marking. Its `generator` and `generated-at` parameters are read in each form
 * MIME allows, plain, quoted, and the RFC 2231 extended and sectioned forms in UTF-8 or US-ASCII.
 * A field with any other value is no marking, and a mail without one yields an empty list.
 *
 * The body is never read, so a quoted mail or an attachment inside it cannot pass for a marking of
 * the mail itself.
 */
final readonly class MailMarkingReader
{
    /**
     * Every marking the header section carries, in the order of its fields.
     *
     * @return list<Marking>
     */
    public function read(string $message): array
    {
        $markings = [];

        foreach ($this->fields($message) as [$name, $value]) {
            $marking = $name === strtolower(MailMarking::HEADER) ? $this->marking($value) : null;

            if ($marking instanceof Marking) {
                $markings[] = $marking;
            }
        }

        return $markings;
    }

    /** Whether the header section carries a marking that says generative AI created or edited the content. */
    public function marksGenerativeAi(string $message): bool
    {
        return array_any(
            $this->read($message),
            static fn (Marking $marking): bool => $marking->sourceType->involvesGenerativeAi(),
        );
    }

    /**
     * The domains of the DKIM signatures whose list of signed fields names the marking's field, in
     * the order of the signatures.
     *
     * A domain here says that its signature claims to cover the field. Whether the signature holds
     * is not checked: that takes the signer's public key from DNS and the mail exactly as it was
     * signed, and a reader of a stored mail cannot count on either.
     *
     * @return list<string>
     */
    public function signingDomains(string $message): array
    {
        $domains = [];

        foreach ($this->fields($message) as [$name, $value]) {
            if ($name !== 'dkim-signature') {
                continue;
            }

            $tags = $this->tags($value);
            $signed = array_map(strtolower(...), explode(':', $tags['h'] ?? ''));
            $domain = strtolower($tags['d'] ?? '');

            if ($domain !== '' && in_array(strtolower(MailMarking::HEADER), $signed, true) && ! in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * The fields of the header section, each as its lowercase name and its value with folded lines
     * joined.
     *
     * @return list<array{string, string}>
     */
    private function fields(string $message): array
    {
        // The header section ends at the first empty line, and what follows it is the body.
        $section = preg_match('/\r?\n\r?\n/', $message, $end, PREG_OFFSET_CAPTURE) === 1
            ? substr($message, 0, $end[0][1])
            : $message;

        // A field goes on over every following line that starts with a space or a tab.
        $unfolded = (string) preg_replace('/\r?\n(?=[ \t])/', '', $section);

        $fields = [];

        foreach (preg_split('/\r?\n/', $unfolded) ?: [] as $line) {
            if (preg_match('/^([\x21-\x39\x3B-\x7E]+):(.*)$/s', $line, $field) === 1) {
                $fields[] = [strtolower($field[1]), trim($field[2])];
            }
        }

        return $fields;
    }

    /**
     * The tags of a DKIM signature by name, with the whitespace a folded value holds taken out. The
     * first occurrence of a tag wins.
     *
     * @return array<string, string>
     */
    private function tags(string $signature): array
    {
        $tags = [];

        foreach (explode(';', $signature) as $tag) {
            $pair = explode('=', $tag, 2);

            if (count($pair) === 2) {
                $tags[strtolower(trim($pair[0]))] ??= (string) preg_replace('/\s+/', '', $pair[1]);
            }
        }

        return $tags;
    }

    /**
     * The marking a field value names, or null when its value is no term of the vocabulary.
     *
     * A moment that is no ISO 8601 date-time is left out rather than guessed at, and the term and
     * the generator still stand.
     */
    private function marking(string $value): ?Marking
    {
        [$uri, $parameters] = $this->parameters($value);
        $sourceType = DigitalSourceType::fromUri($uri);

        if (! $sourceType instanceof DigitalSourceType) {
            return null;
        }

        $generator = $parameters['generator'] ?? null;

        try {
            return Marking::fromArray([
                'digital_source_type' => $sourceType->value,
                'generator' => $generator,
                'generated_at' => $parameters['generated-at'] ?? null,
            ]);
        } catch (InvalidArgumentException) {
            return new Marking($sourceType, $generator);
        }
    }

    /**
     * The value of a field in front of its first parameter, and its parameters by lowercase name,
     * decoded. A parameter whose text cannot be read as UTF-8 is left out.
     *
     * @return array{string, array<string, string>}
     */
    private function parameters(string $value): array
    {
        $segments = $this->segments($value);
        $main = trim(array_shift($segments));
        $plain = [];
        $extended = [];
        $sections = [];

        foreach ($segments as $segment) {
            $pair = explode('=', $segment, 2);

            if (count($pair) !== 2) {
                continue;
            }

            $attribute = strtolower(trim($pair[0]));
            $raw = $this->unquote(trim($pair[1]));

            if (preg_match('/^([^*]+)\*(\d+)(\*?)$/', $attribute, $section) === 1) {
                $sections[$section[1]][(int) $section[2]] ??= [$raw, $section[3] === '*'];
            } elseif (str_ends_with($attribute, '*')) {
                $extended[substr($attribute, 0, -1)] ??= $raw;
            } else {
                $plain[$attribute] ??= $raw;
            }
        }

        $parameters = [];

        foreach ($plain as $name => $raw) {
            $text = $this->text('', $raw);

            if ($text !== null) {
                $parameters[$name] = $text;
            }
        }

        // An extended form says more than a plain value of the same name, so it wins where both
        // stand and it can be read.
        foreach ($extended as $name => $raw) {
            $text = $this->extended($raw);

            if ($text !== null) {
                $parameters[$name] = $text;
            }
        }

        foreach ($sections as $name => $parts) {
            $text = $this->joined($parts);

            if ($text !== null) {
                $parameters[$name] = $text;
            }
        }

        return [$main, $parameters];
    }

    /**
     * A field value cut at every semicolon outside a quoted string.
     *
     * @return non-empty-list<string>
     */
    private function segments(string $value): array
    {
        $segments = [];
        $current = '';
        $quoted = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($quoted && $character === '\\' && $index + 1 < $length) {
                $current .= $character.$value[++$index];

                continue;
            }

            if ($character === ';' && ! $quoted) {
                $segments[] = $current;
                $current = '';

                continue;
            }

            if ($character === '"') {
                $quoted = ! $quoted;
            }

            $current .= $character;
        }

        $segments[] = $current;

        return $segments;
    }

    /** A parameter value without its quotes and with its escapes resolved. */
    private function unquote(string $value): string
    {
        if (strlen($value) < 2 || ! str_starts_with($value, '"') || ! str_ends_with($value, '"')) {
            return $value;
        }

        return (string) preg_replace('/\\\\(.)/s', '$1', substr($value, 1, -1));
    }

    /** An RFC 2231 extended value, `charset'language'percent-encoded text`, decoded. */
    private function extended(string $raw): ?string
    {
        $parts = explode("'", $raw, 3);

        return count($parts) === 3 ? $this->text(strtolower($parts[0]), rawurldecode($parts[2])) : null;
    }

    /**
     * The sections of an RFC 2231 parameter joined and decoded, or null when one is missing: a value
     * with a hole in it would be a generator nobody wrote.
     *
     * @param  array<int, array{string, bool}>  $parts  the raw value of each section by its number, and whether it is percent-encoded
     */
    private function joined(array $parts): ?string
    {
        ksort($parts);

        if (array_keys($parts) !== range(0, count($parts) - 1)) {
            return null;
        }

        $charset = '';
        $text = '';

        foreach ($parts as $number => [$raw, $encoded]) {
            if ($encoded && $number === 0) {
                $prefix = explode("'", $raw, 3);

                if (count($prefix) !== 3) {
                    return null;
                }

                $charset = strtolower($prefix[0]);
                $raw = $prefix[2];
            }

            $text .= $encoded ? rawurldecode($raw) : $raw;
        }

        return $this->text($charset, $text);
    }

    /**
     * Decoded text in a charset the reader takes, UTF-8 or US-ASCII, the latter also where no charset
     * is named; null for any other charset or for bytes that are no UTF-8.
     */
    private function text(string $charset, string $text): ?string
    {
        if (! in_array($charset, ['', 'utf-8', 'us-ascii'], true)) {
            return null;
        }

        return preg_match('//u', $text) === 1 ? $text : null;
    }
}
