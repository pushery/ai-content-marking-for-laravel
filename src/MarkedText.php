<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\HtmlString;
use JsonSerializable;
use Override;
use Pushery\AiContentMarking\Support\Microdata;
use Stringable;

/**
 * A plain text together with the marking that says how it came about.
 *
 * Each place a text is delivered asks for one form of it:
 *
 *  - `html()` for a page: the escaped text inside schema.org microdata that names the IPTC term.
 *  - `toArray()` and JSON for an API: the text beside the marking's values.
 *  - the string itself wherever no marking can travel, such as a `<title>`, an attribute or a
 *    mail subject. Blade's `{{ $text }}` escapes it like any other string.
 *
 * Nothing marks itself: a template or a resource that should carry the marking asks for it, so a
 * text used in an attribute never breaks the markup around it.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class MarkedText implements Arrayable, JsonSerializable, Stringable
{
    public function __construct(
        public string $text,
        public Marking $marking,
    ) {}

    /** The plain text, for every place no marking can travel. */
    #[Override]
    public function __toString(): string
    {
        return $this->text;
    }

    /** A text that generative AI created. */
    public static function generated(string $text, ?string $generator = null, ?DateTimeInterface $at = null): self
    {
        return new self($text, Marking::generated($generator, $at));
    }

    /** A text that generative AI edited. */
    public static function edited(string $text, ?string $generator = null, ?DateTimeInterface $at = null): self
    {
        return new self($text, Marking::edited($generator, $at));
    }

    /**
     * The text, escaped, inside an inline element that carries the marking as microdata.
     *
     * The element is a `span`, which is valid wherever text is: wrap it in the paragraph, heading
     * or list item the page needs.
     */
    public function html(): HtmlString
    {
        return Microdata::wrap(
            htmlspecialchars($this->text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
            $this->marking->sourceType,
            'span',
        );
    }

    /**
     * The text and the marking's values.
     *
     * @return array{text: string, marking: array{digital_source_type: string, digital_source_type_uri: string, generator: string|null, generated_at: string|null}}
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'marking' => $this->marking->toArray(),
        ];
    }

    /**
     * @return array{text: string, marking: array{digital_source_type: string, digital_source_type_uri: string, generator: string|null, generated_at: string|null}}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
