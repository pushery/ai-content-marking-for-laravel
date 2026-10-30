<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Gaps;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Override;
use Pushery\AiContentMarking\Models\ModelEntry;

/**
 * The gap report over a set of outputs: every output with what was found for it, and every model
 * the outputs name with what the register knows about it.
 *
 * `GapReporter` builds it and writes it as Markdown. As an array or as JSON it is the same report in
 * plain values, for an application that keeps or renders it itself.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class GapReport implements Arrayable, JsonSerializable
{
    /**
     * @param  list<Assessment>  $assessments  in the order of the outputs
     * @param  list<ModelEntry>  $models  every model the outputs name, in the order of first use; one the register does not list stands as `Unknown`
     * @param  list<string>  $unlisted  the models among them the register does not list
     * @param  int  $watermarkFromTokens  the length from which a watermark is expected
     */
    public function __construct(
        public array $assessments,
        public array $models,
        public array $unlisted,
        public int $watermarkFromTokens,
    ) {}

    /**
     * The assessments of the outputs that lack a layer of marking.
     *
     * @return list<Assessment>
     */
    public function gaps(): array
    {
        return array_values(array_filter($this->assessments, static fn (Assessment $assessment): bool => $assessment->hasGaps()));
    }

    /**
     * The assessments of the outputs listed as exceptions.
     *
     * @return list<Assessment>
     */
    public function exceptions(): array
    {
        return array_values(array_filter($this->assessments, static fn (Assessment $assessment): bool => $assessment->isException()));
    }

    /** Whether no output lacks a layer of marking. */
    public function hasNoGaps(): bool
    {
        return $this->gaps() === [];
    }

    /**
     * @return array{watermark_from_tokens: int, models: list<array{model: string, watermarks_text: string, source: string|null, checked_on: string|null, listed: bool}>, outputs: list<array{reference: string, model: string, tokens: int, kind: string, metadata: string, exception: bool, very_short_text: bool, gaps: list<string>}>}
     */
    #[Override]
    public function toArray(): array
    {
        $models = [];

        foreach ($this->models as $entry) {
            $models[] = [...$entry->toArray(), 'listed' => ! in_array($entry->model, $this->unlisted, true)];
        }

        $outputs = [];

        foreach ($this->assessments as $assessment) {
            $outputs[] = $assessment->toArray();
        }

        return [
            'watermark_from_tokens' => $this->watermarkFromTokens,
            'models' => $models,
            'outputs' => $outputs,
        ];
    }

    /**
     * @return array{watermark_from_tokens: int, models: list<array{model: string, watermarks_text: string, source: string|null, checked_on: string|null, listed: bool}>, outputs: list<array{reference: string, model: string, tokens: int, kind: string, metadata: string, exception: bool, very_short_text: bool, gaps: list<string>}>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
