<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The words a reader sees for a marking, and the element they appear in.
 *
 * The notice component and the label component ask this class, and an application that renders its
 * own notice resolves it from the container and asks it too. Which source types get a notice and
 * which element holds it come from the configuration. The words come from the package's
 * translations, which an application publishes to change them, or from the application's own
 * catalog through {@see self::fill()}.
 *
 * The notice is a separate piece from the machine-readable marking, on purpose: a tool reads the
 * one and a person reads the other, and a page may need either without the other.
 */
final readonly class Notice
{
    /** The source types that get a notice unless the configuration names others. */
    public const array DEFAULT_SOURCE_TYPES = ['trainedAlgorithmicMedia', 'compositeWithTrainedAlgorithmicMedia'];

    /** The element a notice appears in unless the configuration or the component names another. */
    public const string DEFAULT_ELEMENT = 'p';

    /** The elements a notice may appear in. */
    public const array ELEMENTS = ['p', 'span', 'small', 'div', 'figcaption', 'aside'];

    /** What may stand beside the acronym of a label: the kind of involvement, the sentence of the notice, or nothing. */
    public const array BESIDE = ['kind', 'notice', 'none'];

    public function __construct(
        private Repository $config,
        private Translator $translator,
    ) {}

    /**
     * The words for a marking in the given locale and form, or null where the configuration shows
     * no notice for its source type.
     *
     * Without a form the words are the line beside a result, `notice.<term>`. A form names a sentence
     * of its own, `notice.forms.<form>.<term>`, for a place that says something else: where a person
     * starts to work with a generating system, in an export that travels without the screen, or about
     * the product rather than one result. The package ships the line beside a result; an application
     * writes the forms it needs into its published translations.
     *
     * A sentence can name the values of the marking as `:generator`, `:model` and `:operator`. One that
     * names a value this marking does not carry is refused rather than shown with a gap.
     *
     * @throws InvalidArgumentException when the words are missing, or name a value the marking does not carry
     */
    public function label(Marking $marking, ?string $locale = null, ?string $form = null): ?string
    {
        if (! $this->shows($marking)) {
            return null;
        }

        $type = $marking->sourceType->value;

        $path = $form === null ? 'notice.'.$type : 'notice.forms.'.$form.'.'.$type;
        $key = 'ai-content-marking::messages.'.$path;
        $line = $this->translator->get($key, [], $locale);

        if (! is_string($line) || $line === $key) {
            throw new InvalidArgumentException(sprintf(
                'The notice for `%s` has no words%s. Publish the translations with `php artisan vendor:publish --tag=ai-content-marking-lang` and add them under `%s`.',
                $type,
                $form === null ? '' : " in the form `{$form}`",
                $path,
            ));
        }

        $values = $this->values($marking, $line, "The notice under `{$path}`");
        $label = $this->translator->get($key, $values, $locale);

        return is_string($label) ? $label : $line;
    }

    /**
     * Whether the configuration shows a notice for the marking's source type.
     *
     * {@see self::label()} answers null where it does not. An application that keeps its sentences in
     * its own catalog asks this before it fills one.
     */
    public function shows(Marking $marking): bool
    {
        return in_array($marking->sourceType->value, $this->sourceTypes(), true);
    }

    /**
     * A sentence the application translated itself, filled with the values of a marking.
     *
     * For an application whose texts live in its own catalog, a JSON catalog included, where every
     * key is a literal `__()` call an extractor finds:
     *
     *     $notice->fill(__('Machine-translated with :generator (:model). Review before relying on it.'), $marking)
     *
     * The values and the check are those of {@see self::label()}: `:generator`, `:model` and
     * `:operator`, also written `:Generator` or `:GENERATOR` the way a translation capitalizes them,
     * and a sentence that names a value the marking does not carry is refused rather than shown with
     * a gap. Whether a notice is due at all is {@see self::shows()}.
     *
     * @throws InvalidArgumentException when the sentence names a value the marking does not carry
     */
    public function fill(string $sentence, Marking $marking): string
    {
        $replace = [];

        // The replacements Laravel's translator makes for a line, so a sentence filled here reads as
        // one filled by label(): the name as written, capitalized, and in capitals.
        foreach ($this->values($marking, $sentence, "The sentence `{$sentence}`") as $name => $value) {
            $replace[':'.Str::ucfirst($name)] = Str::ucfirst($value);
            $replace[':'.Str::upper($name)] = Str::upper($value);
            $replace[':'.$name] = $value;
        }

        return strtr($sentence, $replace);
    }

    /**
     * The element a notice appears in: the one asked for, else the configured one.
     *
     * @throws InvalidArgumentException for an element outside self::ELEMENTS
     */
    public function element(?string $asked = null): string
    {
        $element = $asked ?? $this->config->get('ai-content-marking.notice.element', self::DEFAULT_ELEMENT);

        if (! is_string($element) || ! in_array($element, self::ELEMENTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'A notice appears in one of these elements: %s. `%s` is not one of them.',
                implode(', ', self::ELEMENTS),
                is_string($element) ? $element : get_debug_type($element),
            ));
        }

        return $element;
    }

    /**
     * The acronym a label shows as its main element, `label.acronym`.
     *
     * The package ships "AI" in every language, the English acronym the Code of Practice on
     * AI-generated content asks for. Where a national law on the use of languages asks for another,
     * an application writes it into its published translations for that locale.
     *
     * @throws InvalidArgumentException when the acronym is missing
     */
    public function acronym(?string $locale = null): string
    {
        return $this->line('label.acronym', $locale, 'The label has no acronym');
    }

    /**
     * The word beside the acronym that says how generative AI was involved, `label.<term>`, such as
     * "generated" or "modified", or null where the configuration shows no notice for the source type.
     *
     * @throws InvalidArgumentException when the word is missing
     */
    public function kind(Marking $marking, ?string $locale = null): ?string
    {
        if (! $this->shows($marking)) {
            return null;
        }

        $type = $marking->sourceType->value;

        return $this->line('label.'.$type, $locale, "The label for `{$type}` has no words");
    }

    /**
     * What stands beside the acronym of a label: the word of {@see self::kind()} for `kind`, the
     * sentence of {@see self::label()} for `notice`, and nothing for `none`.
     *
     * @throws InvalidArgumentException for anything outside self::BESIDE, or words that are missing
     */
    public function beside(Marking $marking, string $beside = 'kind', ?string $locale = null): ?string
    {
        return match ($beside) {
            'kind' => $this->kind($marking, $locale),
            'notice' => $this->label($marking, $locale),
            'none' => null,
            default => throw new InvalidArgumentException(sprintf(
                'Beside the acronym of a label stands one of these: %s. `%s` is not one of them.',
                implode(', ', self::BESIDE),
                $beside,
            )),
        };
    }

    /**
     * A line of the label in the given locale, refused rather than shown as its key.
     *
     * @throws InvalidArgumentException when the line is missing
     */
    private function line(string $path, ?string $locale, string $missing): string
    {
        $key = 'ai-content-marking::messages.'.$path;
        $line = $this->translator->get($key, [], $locale);

        if (! is_string($line) || $line === $key) {
            throw new InvalidArgumentException(sprintf(
                '%s. Publish the translations with `php artisan vendor:publish --tag=ai-content-marking-lang` and add them under `%s`.',
                $missing,
                $path,
            ));
        }

        return $line;
    }

    /**
     * The values of a marking a sentence may name, after refusing a sentence that names one the
     * marking does not carry.
     *
     * @return array<string, string>
     *
     * @throws InvalidArgumentException when the sentence names a value the marking does not carry
     */
    private function values(Marking $marking, string $sentence, string $where): array
    {
        $values = ['generator' => $marking->generator, 'model' => $marking->model, 'operator' => $marking->operator];

        foreach ($values as $name => $value) {
            if ($value === null && preg_match('/:'.$name.'\b/i', $sentence) === 1) {
                throw new InvalidArgumentException("{$where} names `:{$name}`, and the marking carries no {$name}.");
            }
        }

        return array_filter($values, is_string(...));
    }

    /**
     * The configured source types that get a notice, as their IPTC codes.
     *
     * @return list<string>
     */
    private function sourceTypes(): array
    {
        $configured = $this->config->get('ai-content-marking.notice.source_types', self::DEFAULT_SOURCE_TYPES);

        if (! is_array($configured)) {
            return self::DEFAULT_SOURCE_TYPES;
        }

        return array_values(array_filter($configured, is_string(...)));
    }
}
