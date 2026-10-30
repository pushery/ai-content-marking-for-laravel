<?php

declare(strict_types=1);

return [
    'notice' => [
        'trainedAlgorithmicMedia' => 'Created using Generative AI',
        'compositeWithTrainedAlgorithmicMedia' => 'Edited using Generative AI',
    ],
    'report' => [
        'threshold' => 'A watermark is expected in text of :tokens tokens or more.',
        'model' => 'Model',
        'watermarks_text' => 'Watermarks its text',
        'source' => 'Source',
        'checked_on' => 'Checked on',
        'output' => 'Output',
        'tokens' => 'Tokens',
        'kind' => 'Kind',
        'findings' => 'Findings',
        'not_listed' => 'not in the register',
        'watermarking' => [
            'yes' => 'yes',
            'no' => 'no',
            'unknown' => 'unknown',
        ],
        'kinds' => [
            'generated' => 'generated',
            'edited' => 'edited',
            'translated' => 'translated',
            'corrected' => 'corrected',
        ],
        'gaps' => [
            'watermark-missing' => 'no watermark: the model does not watermark text',
            'watermark-unknown' => 'no known watermark: whether the model watermarks text is not known',
            'metadata-missing' => 'no marking in the metadata of its format',
            'metadata-unsigned' => 'marking in metadata without a signature',
        ],
        'very_short_text' => 'very short text, no watermark expected',
        'exception' => 'listed as an exception',
        'none' => 'no gap',
    ],
];
