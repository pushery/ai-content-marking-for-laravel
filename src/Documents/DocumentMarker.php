<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Documents;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Pushery\AiContentMarking\DigitalSourceType;
use Pushery\AiContentMarking\Marking;
use RuntimeException;
use Throwable;
use UnexpectedValueException;
use ZipArchive;

/**
 * Writes a marking into an Office Open XML document, such as a Word file, and reads it back.
 *
 * The marking goes into the document's custom properties, the part `docProps/custom.xml`, which a
 * program reads without opening the text and Word lists among the document's properties. Each value
 * is a property of its own:
 *
 *     DigitalSourceType         the URI of the IPTC term
 *     DigitalSourceGenerator    the generator, where the marking names one
 *     DigitalSourceModel        the model, where the marking names one
 *     DigitalSourceOperator     the operator, where the marking names one
 *     DigitalSourceGeneratedAt  the moment as an ISO 8601 date-time, where the marking names one
 *
 * A document without custom properties gets the part, declared with its content type and its
 * relationship. A document with them keeps every property of its own, and a marking written before
 * is replaced rather than repeated. Every write is read back.
 *
 * The properties are plain metadata: nothing signs them, and a program that saves the document
 * without them removes them. Reading and writing need the `zip` and `dom` extensions of PHP.
 */
final readonly class DocumentMarker
{
    /** The part that holds the custom properties. */
    public const string PROPERTIES = 'docProps/custom.xml';

    /** The most a part the marker reads may unpack to: custom properties, relationships and content types take kilobytes. */
    public const int LARGEST_PART = 1_048_576;

    public const string TYPE = 'DigitalSourceType';

    public const string GENERATOR = 'DigitalSourceGenerator';

    public const string MODEL = 'DigitalSourceModel';

    public const string OPERATOR = 'DigitalSourceOperator';

    public const string GENERATED_AT = 'DigitalSourceGeneratedAt';

    private const array NAMES = [self::TYPE, self::GENERATOR, self::MODEL, self::OPERATOR, self::GENERATED_AT];

    private const string CONTENT_TYPES = '[Content_Types].xml';

    private const string RELATIONSHIPS = '_rels/.rels';

    private const string CONTENT_TYPES_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/content-types';

    private const string RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const string PROPERTIES_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties';

    private const string VARIANT_TYPES_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes';

    private const string PROPERTIES_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.custom-properties+xml';

    private const string PROPERTIES_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';

    /** The format id every user-defined property carries. */
    private const string USER_DEFINED = '{D5CDD505-2E9C-101B-9397-08002B2CF9AE}';

    /**
     * Both arguments stand in for the machine, and only a test hands them in.
     *
     * @param  (Closure(): ZipArchive)|null  $archive  makes the archive each read and each write opens
     * @param  (Closure(): bool)|null  $extensionsLoaded  says whether the zip and dom extensions of PHP are there
     */
    public function __construct(
        private ?Closure $archive = null,
        private ?Closure $extensionsLoaded = null,
    ) {}

    /**
     * Write a marking into the document at a path, in place.
     *
     * @throws InvalidArgumentException when the file is no Office Open XML document
     * @throws UnexpectedValueException when the document does not hold the marking afterwards
     * @throws RuntimeException when an extension is missing or the document cannot be written
     */
    public function write(string $path, Marking $marking): void
    {
        $this->requireExtensions();
        $zip = $this->open($path);

        try {
            $properties = $this->part($zip, self::PROPERTIES) ?? $this->declaredProperties($zip);
            $this->replace($properties, $marking);
            $this->put($zip, self::PROPERTIES, $properties);
        } catch (Throwable $exception) {
            $zip->unchangeAll();
            $zip->close();

            throw $exception;
        }

        if (! $zip->close()) {
            throw new RuntimeException("The document at {$path} could not be written.");
        }

        $read = $this->read($path);

        if (! $read instanceof Marking || $read->toArray() !== $marking->toArray()) {
            throw new UnexpectedValueException("The document at {$path} does not hold the marking that was written into it.");
        }
    }

    /**
     * The marking the document at a path declares in its custom properties, or null.
     *
     * A property whose value is no term of the vocabulary is no marking, and a moment that is no
     * ISO 8601 date-time is left out.
     *
     * @throws InvalidArgumentException when the file is no Office Open XML document
     * @throws RuntimeException when an extension is missing
     */
    public function read(string $path): ?Marking
    {
        $this->requireExtensions();
        $zip = $this->open($path);

        try {
            $properties = $this->part($zip, self::PROPERTIES);
        } finally {
            $zip->close();
        }

        if (! $properties instanceof DOMDocument) {
            return null;
        }

        $values = [];

        foreach ($this->children($this->root($properties), self::PROPERTIES_NAMESPACE, 'property') as $property) {
            $name = $property->getAttribute('name');

            if (in_array($name, self::NAMES, true) && ! array_key_exists($name, $values)) {
                $values[$name] = trim($property->textContent);
            }
        }

        $sourceType = isset($values[self::TYPE]) ? DigitalSourceType::fromUri($values[self::TYPE]) : null;

        if (! $sourceType instanceof DigitalSourceType) {
            return null;
        }

        return new Marking(
            $sourceType,
            $values[self::GENERATOR] ?? null,
            $this->moment($values[self::GENERATED_AT] ?? null),
            $values[self::MODEL] ?? null,
            $values[self::OPERATOR] ?? null,
        );
    }

    /** @throws RuntimeException when an extension is missing */
    private function requireExtensions(): void
    {
        $loaded = $this->extensionsLoaded instanceof Closure
            ? ($this->extensionsLoaded)()
            : class_exists(ZipArchive::class) && class_exists(DOMDocument::class);

        if (! $loaded) {
            throw new RuntimeException('Marking a document needs the zip and dom extensions of PHP.');
        }
    }

    /** @throws InvalidArgumentException when the file is no Office Open XML document */
    private function open(string $path): ZipArchive
    {
        $zip = $this->archive instanceof Closure ? ($this->archive)() : new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException("The file at {$path} is no Office Open XML document: it is not a zip archive.");
        }

        if ($zip->locateName(self::CONTENT_TYPES) === false || $zip->locateName(self::RELATIONSHIPS) === false) {
            $zip->close();

            throw new InvalidArgumentException("The file at {$path} is no Office Open XML document: it has no [Content_Types].xml or no _rels/.rels.");
        }

        return $zip;
    }

    /** @throws InvalidArgumentException when the part unpacks to more than LARGEST_PART, declares a document type, or is no well-formed XML */
    private function part(ZipArchive $zip, string $name): ?DOMDocument
    {
        // Read one byte past the limit and no further, whatever size the archive claims for the part: an archive
        // built to unpack to gigabytes stops here instead of in the memory of the request.
        $xml = $zip->getFromName($name, self::LARGEST_PART + 1);

        if ($xml === false) {
            return null;
        }

        if (strlen($xml) > self::LARGEST_PART) {
            throw new InvalidArgumentException("The part {$name} of the document unpacks to more than ".self::LARGEST_PART.' bytes.');
        }

        // No part of an Office Open XML document declares a document type, and a declaration is where an entity that
        // expands to gigabytes would be defined, so one is refused before anything parses it.
        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new InvalidArgumentException("The part {$name} of the document declares a document type, which no part of an Office Open XML document carries.");
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded === false || ! $document->documentElement instanceof DOMElement) {
            throw new InvalidArgumentException("The part {$name} of the document is no well-formed XML.");
        }

        return $document;
    }

    /** @throws RuntimeException when the archive refuses the part */
    private function put(ZipArchive $zip, string $name, DOMDocument $document): void
    {
        $xml = $document->saveXML();

        if ($xml === false || ! $zip->addFromString($name, $xml)) {
            throw new RuntimeException("The part {$name} could not be written into the document.");
        }
    }

    /**
     * A custom properties part of its own, declared to the package: its content type and the
     * relationship that points to it, each unless the package already has it.
     *
     * @throws InvalidArgumentException when a part the package declares itself in is no well-formed XML
     * @throws RuntimeException when the archive refuses a part
     */
    private function declaredProperties(ZipArchive $zip): DOMDocument
    {
        $types = $this->part($zip, self::CONTENT_TYPES) ?? throw new InvalidArgumentException('The document has no [Content_Types].xml.');
        $relationships = $this->part($zip, self::RELATIONSHIPS) ?? throw new InvalidArgumentException('The document has no _rels/.rels.');

        if (! $this->childWith($types, self::CONTENT_TYPES_NAMESPACE, 'Override', 'PartName', '/'.self::PROPERTIES) instanceof DOMElement) {
            $override = $types->createElementNS(self::CONTENT_TYPES_NAMESPACE, 'Override');
            $override->setAttribute('PartName', '/'.self::PROPERTIES);
            $override->setAttribute('ContentType', self::PROPERTIES_CONTENT_TYPE);
            $this->root($types)->appendChild($override);
            $this->put($zip, self::CONTENT_TYPES, $types);
        }

        if (! $this->childWith($relationships, self::RELATIONSHIPS_NAMESPACE, 'Relationship', 'Type', self::PROPERTIES_RELATIONSHIP) instanceof DOMElement) {
            $relationship = $relationships->createElementNS(self::RELATIONSHIPS_NAMESPACE, 'Relationship');
            $relationship->setAttribute('Id', $this->freeRelationshipId($relationships));
            $relationship->setAttribute('Type', self::PROPERTIES_RELATIONSHIP);
            $relationship->setAttribute('Target', self::PROPERTIES);
            $this->root($relationships)->appendChild($relationship);
            $this->put($zip, self::RELATIONSHIPS, $relationships);
        }

        $properties = new DOMDocument('1.0', 'UTF-8');
        $root = $properties->createElementNS(self::PROPERTIES_NAMESPACE, 'Properties');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:vt', self::VARIANT_TYPES_NAMESPACE);
        $properties->appendChild($root);

        return $properties;
    }

    /** The marking's properties in place of any the document carried, after every property of its own. */
    private function replace(DOMDocument $properties, Marking $marking): void
    {
        $root = $this->root($properties);
        $pids = [1];

        foreach ($this->children($root, self::PROPERTIES_NAMESPACE, 'property') as $property) {
            if (in_array($property->getAttribute('name'), self::NAMES, true)) {
                $root->removeChild($property);

                continue;
            }

            $pids[] = (int) $property->getAttribute('pid');
        }

        $pid = max($pids);

        // Each element joins the tree before its child does, so the child finds the namespaces the
        // root declares in scope and repeats no declaration of its own.
        foreach ($this->values($marking) as $name => $value) {
            $property = $properties->createElementNS(self::PROPERTIES_NAMESPACE, 'property');
            $property->setAttribute('fmtid', self::USER_DEFINED);
            $property->setAttribute('pid', (string) ++$pid);
            $property->setAttribute('name', $name);
            $root->appendChild($property);

            $text = $properties->createElementNS(self::VARIANT_TYPES_NAMESPACE, 'vt:lpwstr');
            $property->appendChild($text);
            $text->appendChild($properties->createTextNode($value));
        }
    }

    /**
     * The values of a marking by the name of their property, each only where the marking has it.
     *
     * @return array<string, string>
     */
    private function values(Marking $marking): array
    {
        $values = [self::TYPE => $marking->sourceType->iptcUri()];

        if ($marking->generator !== null) {
            $values[self::GENERATOR] = $marking->generator;
        }

        if ($marking->model !== null) {
            $values[self::MODEL] = $marking->model;
        }

        if ($marking->operator !== null) {
            $values[self::OPERATOR] = $marking->operator;
        }

        if ($marking->generatedAt instanceof DateTimeImmutable) {
            $values[self::GENERATED_AT] = $marking->generatedAt->format(DateTimeInterface::ATOM);
        }

        return $values;
    }

    /** A relationship id the package does not use yet. */
    private function freeRelationshipId(DOMDocument $relationships): string
    {
        $taken = array_map(
            static fn (DOMElement $relationship): string => $relationship->getAttribute('Id'),
            $this->children($this->root($relationships), self::RELATIONSHIPS_NAMESPACE, 'Relationship'),
        );
        $id = 'rIdDigitalSource';

        for ($suffix = 2; in_array($id, $taken, true); $suffix++) {
            $id = 'rIdDigitalSource'.$suffix;
        }

        return $id;
    }

    private function childWith(DOMDocument $document, string $namespace, string $name, string $attribute, string $value): ?DOMElement
    {
        foreach ($this->children($this->root($document), $namespace, $name) as $child) {
            if ($child->getAttribute($attribute) === $value) {
                return $child;
            }
        }

        return null;
    }

    /**
     * The child elements of an element by namespace and local name, collected first, so that one of
     * them can be removed while the list is walked.
     *
     * @return list<DOMElement>
     */
    private function children(DOMElement $parent, string $namespace, string $name): array
    {
        $children = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === $namespace && $child->localName === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function root(DOMDocument $document): DOMElement
    {
        return $document->documentElement ?? throw new InvalidArgumentException('A part of the document has no root element.');
    }

    private function moment(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $moment = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        return $moment instanceof DateTimeImmutable && $moment->format(DateTimeInterface::ATOM) === $value ? $moment : null;
    }
}
