<?php

declare(strict_types=1);

return [
    'notice' => [
        'trainedAlgorithmicMedia' => 'Criado com IA generativa',
        'compositeWithTrainedAlgorithmicMedia' => 'Editado com IA generativa',
    ],
    'report' => [
        'threshold' => 'Espera-se uma marca d\'água em textos de :tokens tokens ou mais.',
        'model' => 'Modelo',
        'watermarks_text' => 'Aplica marca d\'água ao seu texto',
        'source' => 'Fonte',
        'checked_on' => 'Verificado em',
        'output' => 'Saída',
        'tokens' => 'Tokens',
        'kind' => 'Tipo',
        'findings' => 'Resultado',
        'not_listed' => 'não consta no registro',
        'watermarking' => [
            'yes' => 'sim',
            'no' => 'não',
            'unknown' => 'desconhecido',
        ],
        'kinds' => [
            'generated' => 'gerado',
            'edited' => 'editado',
            'translated' => 'traduzido',
            'corrected' => 'corrigido',
        ],
        'gaps' => [
            'watermark-missing' => 'sem marca d\'água: o modelo não aplica marca d\'água ao texto',
            'watermark-unknown' => 'sem marca d\'água conhecida: não se sabe se o modelo aplica marca d\'água ao texto',
            'metadata-missing' => 'sem marcação nos metadados do formato',
            'metadata-unsigned' => 'marcação em metadados sem assinatura',
        ],
        'very_short_text' => 'texto muito curto, nenhuma marca d\'água esperada',
        'exception' => 'listado como exceção',
        'none' => 'sem lacunas',
    ],
];
