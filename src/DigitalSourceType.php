<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking;

/**
 * How a piece of content came about, in the IPTC Digital Source Type vocabulary.
 *
 * The cases are the seventeen terms the vocabulary lists as current, each under the code IPTC
 * publishes. IPTC defines them for images, video, audio and text alike. Two of them say that
 * generative AI was involved: `TrainedAlgorithmicMedia` for content it created, and
 * `CompositeWithTrainedAlgorithmicMedia` for content it edited.
 *
 * @see https://cv.iptc.org/newscodes/digitalsourcetype/
 */
enum DigitalSourceType: string
{
    case DigitalCapture = 'digitalCapture';
    case ComputationalCapture = 'computationalCapture';
    case NegativeFilm = 'negativeFilm';
    case PositiveFilm = 'positiveFilm';
    case Print = 'print';
    case HumanEdits = 'humanEdits';
    case CompositeWithTrainedAlgorithmicMedia = 'compositeWithTrainedAlgorithmicMedia';
    case AlgorithmicallyEnhanced = 'algorithmicallyEnhanced';
    case DigitalCreation = 'digitalCreation';
    case DataDrivenMedia = 'dataDrivenMedia';
    case TrainedAlgorithmicMedia = 'trainedAlgorithmicMedia';
    case AlgorithmicMedia = 'algorithmicMedia';
    case ScreenCapture = 'screenCapture';
    case VirtualRecording = 'virtualRecording';
    case Composite = 'composite';
    case CompositeCapture = 'compositeCapture';
    case CompositeSynthetic = 'compositeSynthetic';

    /** The namespace every term of the vocabulary lives under. */
    private const string IPTC_VOCABULARY = 'http://cv.iptc.org/newscodes/digitalsourcetype/';

    /** The namespace of the schema.org members of IPTCDigitalSourceEnumeration. */
    private const string SCHEMA_ORG = 'https://schema.org/';

    /**
     * The term this URI names, read from an IPTC term URI or from the matching schema.org member.
     *
     * Returns null for any other URI, a withdrawn term included: a reader that guessed would report
     * an origin nobody declared.
     */
    public static function fromUri(string $uri): ?self
    {
        return array_find(
            self::cases(),
            static fn (self $case): bool => $uri === $case->iptcUri() || $uri === $case->schemaOrgUri(),
        );
    }

    /** The term's URI in the IPTC vocabulary. */
    public function iptcUri(): string
    {
        return self::IPTC_VOCABULARY.$this->value;
    }

    /**
     * The schema.org member of IPTCDigitalSourceEnumeration that matches this term.
     *
     * Null for `HumanEdits` and `DigitalCreation`, the two current terms schema.org has no member
     * for. Two members carry names that differ from their term: `computationalCapture` is
     * `MultiFrameComputationalCaptureDigitalSource`, and `composite` is `CompositeDigitalSource`,
     * which the general rule already produces.
     */
    public function schemaOrgUri(): ?string
    {
        return match ($this) {
            self::HumanEdits, self::DigitalCreation => null,
            self::ComputationalCapture => self::SCHEMA_ORG.'MultiFrameComputationalCaptureDigitalSource',
            default => self::SCHEMA_ORG.ucfirst($this->value).'DigitalSource',
        };
    }

    /**
     * The URI a marking in HTML points at: the schema.org member, or the IPTC term where
     * schema.org has none.
     */
    public function uri(): string
    {
        return $this->schemaOrgUri() ?? $this->iptcUri();
    }

    /** Whether generative AI created the content or edited it. */
    public function involvesGenerativeAi(): bool
    {
        return $this === self::TrainedAlgorithmicMedia || $this === self::CompositeWithTrainedAlgorithmicMedia;
    }
}
