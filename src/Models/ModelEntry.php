<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Models;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Override;

/**
 * What the application knows about one model: whether it watermarks the text it writes, where that
 * statement comes from, and the day it was last checked.
 *
 * @implements Arrayable<string, string|null>
 */
final readonly class ModelEntry implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $model,
        public Watermarking $watermarksText,
        public ?string $source = null,
        public ?DateTimeImmutable $checkedOn = null,
    ) {}

    /**
     * The entry as plain values, the day as YYYY-MM-DD.
     *
     * @return array{model: string, watermarks_text: string, source: string|null, checked_on: string|null}
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'watermarks_text' => $this->watermarksText->value,
            'source' => $this->source,
            'checked_on' => $this->checkedOn?->format('Y-m-d'),
        ];
    }

    /**
     * @return array{model: string, watermarks_text: string, source: string|null, checked_on: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
