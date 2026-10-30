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
     * The words for a marking in the given locale, or null where the configuration shows no notice
     * for its source type.
     *
     * @throws InvalidArgumentException when a source type that gets a notice has no words in the translations
     */
    public function label(Marking $marking, ?string $locale = null): ?string
    {
        $type = $marking->sourceType->value;

        if (! in_array($type, $this->sourceTypes(), true)) {
            return null;
        }

        $key = 'ai-content-marking::messages.notice.'.$type;
        $label = $this->translator->get($key, [], $locale);

        if (! is_string($label) || $label === $key) {
            throw new InvalidArgumentException("The notice for `{$type}` has no words. Publish the translations with `php artisan vendor:publish --tag=ai-content-marking-lang` and add them under `notice.{$type}`.");
        }

        return $label;
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
