<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * What the application knows about each model it uses: whether the model watermarks the text it
 * writes, where that statement comes from, and the day it was last checked.
 *
 * The entries come from `ai-content-marking.models`. The package ships none, because whether a
 * model watermarks text is for its provider to state and for the application to check. A model the
 * register does not list reads as `Unknown`.
 */
final readonly class ModelRegister
{
    /**
     * @param  array<string, ModelEntry>  $entries  by the name of the model
     */
    public function __construct(
        private array $entries = [],
    ) {}

    /**
     * The register the `ai-content-marking.models` section of the configuration describes.
     *
     * @param  array<array-key, mixed>  $models
     *
     * @throws InvalidArgumentException naming the first entry that does not have the shape the configuration file documents
     */
    public static function fromConfig(array $models): self
    {
        $entries = [];

        foreach ($models as $model => $entry) {
            if (! is_string($model) || $model === '' || ! is_array($entry)) {
                throw new InvalidArgumentException('Each entry of `ai-content-marking.models` is an array under the name of a model.');
            }

            $entries[$model] = self::parsed($model, $entry);
        }

        return new self($entries);
    }

    /** The entry for a model, or null when the register does not list it. */
    public function entry(string $model): ?ModelEntry
    {
        return $this->entries[$model] ?? null;
    }

    /** Whether a model watermarks the text it writes, `Unknown` for a model the register does not list. */
    public function watermarking(string $model): Watermarking
    {
        return $this->entry($model)->watermarksText ?? Watermarking::Unknown;
    }

    /**
     * Every entry, in the order of the configuration.
     *
     * @return list<ModelEntry>
     */
    public function entries(): array
    {
        return array_values($this->entries);
    }

    /**
     * @param  array<array-key, mixed>  $entry
     *
     * @throws InvalidArgumentException when a value has the wrong type or form
     */
    private static function parsed(string $model, array $entry): ModelEntry
    {
        $watermarks = $entry['watermarks_text'] ?? null;
        $watermarking = is_string($watermarks) ? Watermarking::tryFrom($watermarks) : null;

        if (! $watermarking instanceof Watermarking) {
            throw new InvalidArgumentException("The model `{$model}` in `ai-content-marking.models` needs `watermarks_text` set to `yes`, `no` or `unknown`.");
        }

        $source = $entry['source'] ?? null;

        if ($source !== null && ! is_string($source)) {
            throw new InvalidArgumentException("The `source` of the model `{$model}` in `ai-content-marking.models` is a string or null.");
        }

        $checkedOn = $entry['checked_on'] ?? null;

        if ($checkedOn !== null && ! is_string($checkedOn)) {
            throw new InvalidArgumentException("The `checked_on` of the model `{$model}` in `ai-content-marking.models` is a date as YYYY-MM-DD or null.");
        }

        return new ModelEntry($model, $watermarking, $source, $checkedOn === null ? null : self::day($model, $checkedOn));
    }

    /**
     * @throws InvalidArgumentException when the value is no day that exists, written as YYYY-MM-DD
     */
    private static function day(string $model, string $value): DateTimeImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        // The 30th of February parses with a warning and rolls over into March. Taken as it rolled,
        // it would be a day nobody checked on.
        if (! $day instanceof DateTimeImmutable || ($errors !== false && $errors['warning_count'] > 0)) {
            throw new InvalidArgumentException("The `checked_on` of the model `{$model}` in `ai-content-marking.models` is a date as YYYY-MM-DD, such as 2026-10-15, not `{$value}`.");
        }

        return $day;
    }
}
