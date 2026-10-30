<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking;

use LengthException;

/**
 * Finds the markings an HTML document carries as schema.org microdata.
 *
 * It reports every `link` element whose `itemprop` names `digitalSourceType` and whose `href` is
 * an IPTC Digital Source Type term or the schema.org member that matches one. That is the form
 * `MarkedText::html()` and `Marking::wrap()` write, and the form any other system writing the
 * same microdata uses. A `link` with another property, or with an address outside the vocabulary,
 * is no marking, and a document without one yields an empty list.
 *
 * It reads tags, not a DOM. Attributes count in any order and in each quoting HTML allows. What a
 * browser never renders as an element is skipped: comments, and the contents of `script`, `style`,
 * `template` and `textarea`.
 */
final readonly class MarkingReader
{
    /** The size of document the reader scans unless the configuration says otherwise: 2 MiB. */
    public const int DEFAULT_MAX_BYTES = 2_097_152;

    public function __construct(
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {}

    /**
     * Every marking in the document, in document order.
     *
     * @return list<Marking>
     *
     * @throws LengthException when the document is larger than the reader scans
     */
    public function read(string $html): array
    {
        if (strlen($html) > $this->maxBytes) {
            throw new LengthException(sprintf(
                'The document is %d bytes, more than the %d the reader scans. Raise ai-content-marking.reader.max_bytes to read it.',
                strlen($html),
                $this->maxBytes,
            ));
        }

        $rendered = (string) preg_replace(
            ['/<!--.*?-->/s', '/<(script|style|template|textarea)\b[^>]*>.*?<\/\1\s*>/is'],
            '',
            $html,
        );

        preg_match_all('/<link\b([^>]*)>/i', $rendered, $tags);

        $markings = [];

        foreach ($tags[1] as $attributes) {
            $sourceType = $this->sourceTypeOf($this->attributes($attributes));

            if ($sourceType instanceof DigitalSourceType) {
                $markings[] = new Marking($sourceType);
            }
        }

        return $markings;
    }

    /**
     * Whether the document carries a marking that says generative AI created or edited content.
     *
     * @throws LengthException when the document is larger than the reader scans
     */
    public function marksGenerativeAi(string $html): bool
    {
        return array_any(
            $this->read($html),
            static fn (Marking $marking): bool => $marking->sourceType->involvesGenerativeAi(),
        );
    }

    /**
     * The term a `link` element names, when its attributes make it a marking.
     *
     * @param  array<string, string>  $attributes
     */
    private function sourceTypeOf(array $attributes): ?DigitalSourceType
    {
        // `itemprop` holds a list of names separated by spaces, and the name is case-sensitive.
        $namesTheProperty = preg_match('/(?:^|\s)digitalSourceType(?:\s|$)/', $attributes['itemprop'] ?? '') === 1;

        if (! $namesTheProperty || ! isset($attributes['href'])) {
            return null;
        }

        return DigitalSourceType::fromUri(trim($attributes['href']));
    }

    /**
     * The attributes of a start tag, by lowercase name, with their values decoded.
     *
     * The first occurrence of a name wins, as it does in a browser.
     *
     * @return array<string, string>
     */
    private function attributes(string $source): array
    {
        preg_match_all(
            '/([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        $attributes = [];

        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            $value = ($match[2] ?? '').($match[3] ?? '').($match[4] ?? '');

            $attributes[$name] ??= html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $attributes;
    }
}
