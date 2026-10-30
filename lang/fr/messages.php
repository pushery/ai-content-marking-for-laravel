<?php

declare(strict_types=1);

return [
    'notice' => [
        'trainedAlgorithmicMedia' => 'Créé avec l\'IA générative',
        'compositeWithTrainedAlgorithmicMedia' => 'Modifié avec l\'IA générative',
    ],
    'report' => [
        'threshold' => 'Un filigrane est attendu dans les textes de :tokens tokens ou plus.',
        'model' => 'Modèle',
        'watermarks_text' => 'Applique un filigrane à son texte',
        'source' => 'Source',
        'checked_on' => 'Vérifié le',
        'output' => 'Sortie',
        'tokens' => 'Tokens',
        'kind' => 'Type',
        'findings' => 'Constat',
        'not_listed' => 'absent du registre',
        'watermarking' => [
            'yes' => 'oui',
            'no' => 'non',
            'unknown' => 'inconnu',
        ],
        'kinds' => [
            'generated' => 'généré',
            'edited' => 'retravaillé',
            'translated' => 'traduit',
            'corrected' => 'corrigé',
        ],
        'gaps' => [
            'watermark-missing' => 'pas de filigrane : le modèle n\'applique pas de filigrane au texte',
            'watermark-unknown' => 'pas de filigrane connu : on ignore si le modèle applique un filigrane au texte',
            'metadata-missing' => 'pas de marquage dans les métadonnées de son format',
            'metadata-unsigned' => 'marquage dans des métadonnées non signées',
        ],
        'very_short_text' => 'texte très court, aucun filigrane attendu',
        'exception' => 'consigné comme exception',
        'none' => 'aucune lacune',
    ],
];
