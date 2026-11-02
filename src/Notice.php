<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use InvalidArgumentException;

/**
 * The words a reader sees for a marking, and the element they appear in.
 *
 * The notice component asks this class, and an application that renders its own notice resolves it
 * from the container and asks it too. Which source types get a notice and which element holds it
 * come from the configuration. The words come from the package's translations, which an application
 * publishes to change them.
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
        $type = $marking->sourceType->value;

        if (! in_array($type, $this->sourceTypes(), true)) {
            return null;
        }

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

        $values = ['generator' => $marking->generator, 'model' => $marking->model, 'operator' => $marking->operator];

        foreach ($values as $name => $value) {
            if ($value === null && preg_match('/:'.$name.'\b/i', $line) === 1) {
                throw new InvalidArgumentException("The notice under `{$path}` names `:{$name}`, and the marking carries no {$name}.");
            }
        }

        $label = $this->translator->get($key, array_filter($values, is_string(...)), $locale);

        return is_string($label) ? $label : $line;
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
