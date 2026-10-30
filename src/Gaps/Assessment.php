<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Gaps;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Override;

/**
 * What the gap report found for one output: the layers of marking it lacks, and whether it is very
 * short text or an exception.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Assessment implements Arrayable, JsonSerializable
{
    /**
     * @param  list<Gap>  $gaps  in the order the report checks them, watermark before metadata
     * @param  bool  $veryShortText  whether the output is shorter than the length from which a watermark is expected
     */
    public function __construct(
        public Output $output,
        public array $gaps,
        public bool $veryShortText,
    ) {}

    /** Whether the output is listed as an exception rather than assessed. */
    public function isException(): bool
    {
        return $this->output->kind->isException();
    }

    /** Whether the output lacks a layer of marking. */
    public function hasGaps(): bool
    {
        return $this->gaps !== [];
    }

    /**
     * The output's values with what the report found.
     *
     * @return array{reference: string, model: string, tokens: int, kind: string, metadata: string, exception: bool, very_short_text: bool, gaps: list<string>}
     */
    #[Override]
    public function toArray(): array
    {
        return [
            ...$this->output->toArray(),
            'exception' => $this->isException(),
            'very_short_text' => $this->veryShortText,
            'gaps' => array_map(static fn (Gap $gap): string => $gap->value, $this->gaps),
        ];
    }

    /**
     * @return array{reference: string, model: string, tokens: int, kind: string, metadata: string, exception: bool, very_short_text: bool, gaps: list<string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
