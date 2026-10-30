<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use Pushery\AiContentMarking\Casts\AsMarking;
use Pushery\AiContentMarking\Support\Microdata;

/**
 * What a piece of content declares about its origin: the IPTC term, and, where the caller knows
 * them, the system that generated it and the moment it did.
 *
 * A marking is a value. It carries no wording for readers and decides nothing about where it is
 * shown: `MarkedText` delivers it with a text, and the notice component turns it into a label.
 *
 * @implements Arrayable<string, string|null>
 */
final readonly class Marking implements Arrayable, Castable, JsonSerializable
{
    public function __construct(
        public DigitalSourceType $sourceType,
        public ?string $generator = null,
        public ?DateTimeImmutable $generatedAt = null,
    ) {}

    /** Content that generative AI created. */
    public static function generated(?string $generator = null, ?DateTimeInterface $at = null): self
    {
        return new self(DigitalSourceType::TrainedAlgorithmicMedia, $generator, self::immutable($at));
    }

    /** Content that a person or a program wrote and generative AI then edited. */
    public static function edited(?string $generator = null, ?DateTimeInterface $at = null): self
    {
        return new self(DigitalSourceType::CompositeWithTrainedAlgorithmicMedia, $generator, self::immutable($at));
    }

    /**
     * Read a marking back from the shape `toArray()` writes.
     *
     * The URI is not read: it follows from the term, and a stored URI that disagreed with its term
     * would be a second answer to the same question.
     *
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidArgumentException when the term is missing or unknown, or a value has the wrong type
     */
    public static function fromArray(array $data): self
    {
        $term = $data['digital_source_type'] ?? null;
        $sourceType = is_string($term) ? DigitalSourceType::tryFrom($term) : null;

        if (! $sourceType instanceof DigitalSourceType) {
            throw new InvalidArgumentException('A marking needs `digital_source_type` set to a term of the IPTC Digital Source Type vocabulary, such as `trainedAlgorithmicMedia`.');
        }

        $generator = $data['generator'] ?? null;

        if ($generator !== null && ! is_string($generator)) {
            throw new InvalidArgumentException('The `generator` of a marking is a string or null.');
        }

        $generatedAt = $data['generated_at'] ?? null;

        if ($generatedAt !== null && ! is_string($generatedAt)) {
            throw new InvalidArgumentException('The `generated_at` of a marking is an ISO 8601 date-time string or null.');
        }

        return new self($sourceType, $generator, $generatedAt === null ? null : self::moment($generatedAt));
    }

    /**
     * Laravel's casts call this when a model names the class in `$casts`.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return class-string<AsMarking>
     */
    #[Override]
    public static function castUsing(array $arguments): string
    {
        return AsMarking::class;
    }

    /**
     * HTML the caller vouches for, inside a block element that carries this marking as microdata.
     *
     * The content is not escaped. Pass HTML that is already safe to render, such as rich text the
     * application sanitized; a plain text goes through `MarkedText::html()`, which escapes it.
     */
    public function wrap(Htmlable $html): HtmlString
    {
        return Microdata::wrap($html->toHtml(), $this->sourceType, 'div');
    }

    /**
     * The marking as plain values: the IPTC term, its URI, the generator and the moment, the last
     * written as an ISO 8601 date-time.
     *
     * @return array{digital_source_type: string, digital_source_type_uri: string, generator: string|null, generated_at: string|null}
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'digital_source_type' => $this->sourceType->value,
            'digital_source_type_uri' => $this->sourceType->iptcUri(),
            'generator' => $this->generator,
            'generated_at' => $this->generatedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{digital_source_type: string, digital_source_type_uri: string, generator: string|null, generated_at: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function immutable(?DateTimeInterface $moment): ?DateTimeImmutable
    {
        return $moment instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($moment) : null;
    }

    private static function moment(string $value): DateTimeImmutable
    {
        $moment = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        $errors = DateTimeImmutable::getLastErrors();

        // A date that does not exist, such as the 30th of February, parses with a warning and
        // rolls over into the next month. Taken as it rolled, it would be a moment nobody stored.
        if (! $moment instanceof DateTimeImmutable || ($errors !== false && $errors['warning_count'] > 0)) {
            throw new InvalidArgumentException("The `generated_at` of a marking is an ISO 8601 date-time such as 2026-10-14T21:00:00+00:00, not `{$value}`.");
        }

        return $moment;
    }
}
