<?php

declare(strict_types=1);

return [
    'notice' => [
        'trainedAlgorithmicMedia' => 'Creato con l\'IA generativa',
        'compositeWithTrainedAlgorithmicMedia' => 'Modificato con l\'IA generativa',
    ],
    'label' => [
        'acronym' => 'AI',
        'trainedAlgorithmicMedia' => 'generato',
        'compositeWithTrainedAlgorithmicMedia' => 'modificato',
    ],
    'report' => [
        'threshold' => 'Si prevede una filigrana nei testi di :tokens token o più.',
        'model' => 'Modello',
        'watermarks_text' => 'Applica una filigrana al proprio testo',
        'source' => 'Fonte',
        'checked_on' => 'Verificato il',
        'output' => 'Output',
        'tokens' => 'Token',
        'kind' => 'Tipo',
        'findings' => 'Esito',
        'not_listed' => 'non presente nel registro',
        'watermarking' => [
            'yes' => 'sì',
            'no' => 'no',
            'unknown' => 'sconosciuto',
        ],
        'kinds' => [
            'generated' => 'generato',
            'edited' => 'rielaborato',
            'translated' => 'tradotto',
            'corrected' => 'corretto',
        ],
        'gaps' => [
            'watermark-missing' => 'nessuna filigrana: il modello non applica filigrane al testo',
            'watermark-unknown' => 'filigrana sconosciuta: il registro non indica se il modello applichi filigrane al suo testo',
            'metadata-missing' => 'nessuna marcatura nei metadati del formato',
            'metadata-unsigned' => 'marcatura in metadati senza firma',
        ],
        'very_short_text' => 'testo molto breve, nessuna filigrana prevista',
        'exception' => 'registrato come eccezione',
        'none' => 'nessuna lacuna',
    ],
];
