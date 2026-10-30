<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Gaps;

use Illuminate\Contracts\Translation\Translator;
use InvalidArgumentException;
use Pushery\AiContentMarking\Models\ModelEntry;
use Pushery\AiContentMarking\Models\ModelRegister;
use Pushery\AiContentMarking\Models\Watermarking;

/**
 * Assesses the outputs of models against the two layers of marking the European Commission's Code
 * of Practice on the transparency of content that AI generates asks of text, and writes the result as
 * a report.
 *
 * Measure 1.1 of the Code asks for digitally signed metadata where the format of the content carries
 * metadata (Sub-measure 1.1.1), and for an imperceptible watermark except in very short text
 * (Sub-measure 1.1.2), which the Code's glossary sets at fewer than 200 tokens. Free-form text
 * carries no metadata, so for it the watermark is the one layer. Each output is taken in turn:
 *
 *  - an output of a kind listed as an exception is named as one and not assessed;
 *  - an output of `watermarkFromTokens` tokens or more lacks a watermark unless the register says
 *    its model watermarks text, and the report says whether the model does not or whether that is
 *    not known;
 *  - an output whose format carries metadata lacks that layer when the marking is missing from it or
 *    stands in it without a signature.
 *
 * It decides nothing beyond that. Whether an output really is an exception, whether the register is
 * right about a model and whether a gap is acceptable stay with the application, and the report is
 * the record those judgments are made on.
 */
final readonly class GapReporter
{
    /** The length, in tokens, from which a watermark is expected unless the configuration says otherwise. */
    public const int DEFAULT_WATERMARK_FROM_TOKENS = 200;

    public function __construct(
        private ModelRegister $register,
        private Translator $translator,
        private int $watermarkFromTokens = self::DEFAULT_WATERMARK_FROM_TOKENS,
    ) {}

    /**
     * The report over the outputs, in their order.
     *
     * @param  iterable<Output>  $outputs
     */
    public function assess(iterable $outputs): GapReport
    {
        $assessments = [];
        $models = [];
        $unlisted = [];

        foreach ($outputs as $output) {
            $assessments[] = $this->assessment($output);

            if (isset($models[$output->model])) {
                continue;
            }

            $entry = $this->register->entry($output->model);

            if (! $entry instanceof ModelEntry) {
                $unlisted[] = $output->model;
            }

            $models[$output->model] = $entry ?? new ModelEntry($output->model, Watermarking::Unknown);
        }

        return new GapReport($assessments, array_values($models), $unlisted, $this->watermarkFromTokens);
    }

    /**
     * The report as Markdown in the given locale: the length from which a watermark is expected, a
     * table of the models and what is known about them, and a table of the outputs with what was
     * found for each.
     *
     * A cell holds its value as given, except that a pipe and a backslash are escaped and a line
     * break becomes a space, so no value can end its row or its table.
     *
     * @throws InvalidArgumentException when the translations lack a word the report uses
     */
    public function markdown(GapReport $report, ?string $locale = null): string
    {
        $lines = [
            $this->word('threshold', $locale, ['tokens' => (string) $report->watermarkFromTokens]),
            '',
            $this->row([$this->word('model', $locale), $this->word('watermarks_text', $locale), $this->word('source', $locale), $this->word('checked_on', $locale)]),
            '| --- | --- | --- | --- |',
        ];

        foreach ($report->models as $entry) {
            $watermarks = $this->word('watermarking.'.$entry->watermarksText->value, $locale);

            if (in_array($entry->model, $report->unlisted, true)) {
                $watermarks .= ', '.$this->word('not_listed', $locale);
            }

            $lines[] = $this->row([$entry->model, $watermarks, $entry->source ?? '', $entry->checkedOn?->format('Y-m-d') ?? '']);
        }

        $lines[] = '';
        $lines[] = $this->row([$this->word('output', $locale), $this->word('model', $locale), $this->word('tokens', $locale), $this->word('kind', $locale), $this->word('findings', $locale)]);
        $lines[] = '| --- | --- | --- | --- | --- |';

        foreach ($report->assessments as $assessment) {
            $output = $assessment->output;

            $lines[] = $this->row([
                $output->reference,
                $output->model,
                (string) $output->tokens,
                $this->word('kinds.'.$output->kind->value, $locale),
                $this->findings($assessment, $locale),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    private function assessment(Output $output): Assessment
    {
        $veryShortText = $output->tokens < $this->watermarkFromTokens;

        if ($output->kind->isException()) {
            return new Assessment($output, [], $veryShortText);
        }

        $gaps = [];

        if (! $veryShortText) {
            $watermark = match ($this->register->watermarking($output->model)) {
                Watermarking::Yes => null,
                Watermarking::No => Gap::WatermarkMissing,
                Watermarking::Unknown => Gap::WatermarkUnknown,
            };

            if ($watermark instanceof Gap) {
                $gaps[] = $watermark;
            }
        }

        $metadata = match ($output->metadata) {
            Metadata::Missing => Gap::MetadataMissing,
            Metadata::Unsigned => Gap::MetadataUnsigned,
            Metadata::Signed, Metadata::NotCarried => null,
        };

        if ($metadata instanceof Gap) {
            $gaps[] = $metadata;
        }

        return new Assessment($output, $gaps, $veryShortText);
    }

    /** What was found for one output, as the words of the findings column. */
    private function findings(Assessment $assessment, ?string $locale): string
    {
        if ($assessment->isException()) {
            return $this->word('exception', $locale);
        }

        $findings = array_map(fn (Gap $gap): string => $this->word('gaps.'.$gap->value, $locale), $assessment->gaps);

        if ($assessment->veryShortText) {
            array_unshift($findings, $this->word('very_short_text', $locale));
        }

        return $findings === [] ? $this->word('none', $locale) : implode('; ', $findings);
    }

    /**
     * @param  list<string>  $cells
     */
    private function row(array $cells): string
    {
        $escaped = array_map(
            static fn (string $cell): string => str_replace(['\\', '|', "\r\n", "\r", "\n"], ['\\\\', '\\|', ' ', ' ', ' '], $cell),
            $cells,
        );

        return '| '.implode(' | ', $escaped).' |';
    }

    /**
     * A word of the report from the package's translations.
     *
     * @param  array<string, string>  $replace
     *
     * @throws InvalidArgumentException when the translations lack it
     */
    private function word(string $key, ?string $locale, array $replace = []): string
    {
        $full = 'ai-content-marking::messages.report.'.$key;
        $word = $this->translator->get($full, $replace, $locale);

        if (! is_string($word) || $word === $full) {
            throw new InvalidArgumentException("The gap report has no words for `report.{$key}`. Publish the translations with `php artisan vendor:publish --tag=ai-content-marking-lang` and add them.");
        }

        return $word;
    }
}
