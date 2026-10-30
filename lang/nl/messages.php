<?php

declare(strict_types=1);

return [
    'notice' => [
        'trainedAlgorithmicMedia' => 'Gemaakt met generatieve AI',
        'compositeWithTrainedAlgorithmicMedia' => 'Bewerkt met generatieve AI',
    ],
    'report' => [
        'threshold' => 'Een watermerk wordt verwacht in tekst vanaf :tokens tokens.',
        'model' => 'Model',
        'watermarks_text' => 'Voorziet zijn tekst van een watermerk',
        'source' => 'Bron',
        'checked_on' => 'Gecontroleerd op',
        'output' => 'Uitvoer',
        'tokens' => 'Tokens',
        'kind' => 'Soort',
        'findings' => 'Bevinding',
        'not_listed' => 'niet in het register',
        'watermarking' => [
            'yes' => 'ja',
            'no' => 'nee',
            'unknown' => 'onbekend',
        ],
        'kinds' => [
            'generated' => 'gegenereerd',
            'edited' => 'bewerkt',
            'translated' => 'vertaald',
            'corrected' => 'gecorrigeerd',
        ],
        'gaps' => [
            'watermark-missing' => 'geen watermerk: het model voorziet tekst niet van een watermerk',
            'watermark-unknown' => 'geen bekend watermerk: onbekend of het model tekst van een watermerk voorziet',
            'metadata-missing' => 'geen markering in de metadata van het formaat',
            'metadata-unsigned' => 'markering in metadata zonder handtekening',
        ],
        'very_short_text' => 'zeer korte tekst, geen watermerk verwacht',
        'exception' => 'als uitzondering vermeld',
        'none' => 'geen leemte',
    ],
];
