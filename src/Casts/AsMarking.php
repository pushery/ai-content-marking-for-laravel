<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Override;
use Pushery\AiContentMarking\Marking;

/**
 * Stores a marking as JSON in one column of a model and reads it back.
 *
 * A model names `Marking::class` in its casts, and Laravel resolves this class through
 * `Marking::castUsing()`. The column holds what `Marking::toArray()` writes, so a column that a
 * query reads directly carries the same keys as the package's JSON. Null stays null.
 *
 * The value set is typed `mixed` rather than `Marking|null`, because a model hands the cast whatever
 * the application assigned, and an assignment of anything else is refused here rather than stored.
 *
 * @implements CastsAttributes<Marking|null, mixed>
 */
final class AsMarking implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException when the column holds something other than a stored marking
     */
    #[Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Marking
    {
        if ($value === null) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($data)) {
            throw new InvalidArgumentException(sprintf('The column `%s` of %s holds no marking: a marking is stored as a JSON object.', $key, $model::class));
        }

        return Marking::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException when the value is neither a marking nor null
     */
    #[Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Marking) {
            throw new InvalidArgumentException(sprintf('The column `%s` of %s takes a %s or null, not %s.', $key, $model::class, Marking::class, get_debug_type($value)));
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
