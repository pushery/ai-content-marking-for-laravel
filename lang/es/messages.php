<?php

declare(strict_types=1);

return [
    'notice' => [
        'trainedAlgorithmicMedia' => 'Creado con IA generativa',
        'compositeWithTrainedAlgorithmicMedia' => 'Editado con IA generativa',
    ],
    'report' => [
        'threshold' => 'Se espera una marca de agua en textos de :tokens tokens o más.',
        'model' => 'Modelo',
        'watermarks_text' => 'Aplica marca de agua a su texto',
        'source' => 'Fuente',
        'checked_on' => 'Comprobado el',
        'output' => 'Salida',
        'tokens' => 'Tokens',
        'kind' => 'Tipo',
        'findings' => 'Resultado',
        'not_listed' => 'no figura en el registro',
        'watermarking' => [
            'yes' => 'sí',
            'no' => 'no',
            'unknown' => 'desconocido',
        ],
        'kinds' => [
            'generated' => 'generado',
            'edited' => 'editado',
            'translated' => 'traducido',
            'corrected' => 'corregido',
        ],
        'gaps' => [
            'watermark-missing' => 'sin marca de agua: el modelo no aplica marcas de agua al texto',
            'watermark-unknown' => 'sin marca de agua conocida: se desconoce si el modelo aplica marcas de agua al texto',
            'metadata-missing' => 'sin marcado en los metadatos de su formato',
            'metadata-unsigned' => 'marcado en metadatos sin firma',
        ],
        'very_short_text' => 'texto muy corto, no se espera marca de agua',
        'exception' => 'registrado como excepción',
        'none' => 'sin carencias',
    ],
];
