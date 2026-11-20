<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
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
 * Two values of the column count as the same when they hold the same marking, whatever the text of
 * their JSON. A `jsonb` column of PostgreSQL and a `json` column of MySQL hand the object back in a
 * spelling of their own, so a model that only read its marking would otherwise count as changed and
 * write its row again on `save()`.
 *
 * @implements CastsAttributes<Marking|null, mixed>
 */
final class AsMarking implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * @param  mixed  $value  the column's value as the connection returns it, which the interface of a cast leaves untyped
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
     * @param  mixed  $value  what the application assigns to the attribute, which the interface of a cast leaves untyped
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

    /**
     * Whether two values of the column hold the same marking. A value that holds none is compared as
     * it is.
     *
     * @param  mixed  $firstValue  a raw value of the column, which the interface of a cast leaves untyped
     * @param  mixed  $secondValue  the other raw value, untyped for the same reason
     */
    #[Override]
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->meaning($firstValue) === $this->meaning($secondValue);
    }

    /**
     * The marking a value of the column holds, in the array form it is stored in, or the value itself
     * where it holds no marking.
     *
     * @param  mixed  $value  a raw value of the column, which may hold anything a connection returns
     * @return mixed the array form of the marking, or the raw value where it holds none
     */
    private function meaning(mixed $value): mixed
    {
        $data = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($data)) {
            return $value;
        }

        try {
            return Marking::fromArray($data)->toArray();
        } catch (InvalidArgumentException) {
            return $value;
        }
    }
}
