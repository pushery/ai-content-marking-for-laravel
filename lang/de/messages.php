<?php

declare(strict_types=1);

return [
    'notice' => [
        'trainedAlgorithmicMedia' => 'Mit generativer KI erstellt',
        'compositeWithTrainedAlgorithmicMedia' => 'Mit generativer KI bearbeitet',
    ],
    'report' => [
        'threshold' => 'Ein Wasserzeichen wird in Text ab :tokens Token erwartet.',
        'model' => 'Modell',
        'watermarks_text' => 'Versieht seinen Text mit Wasserzeichen',
        'source' => 'Quelle',
        'checked_on' => 'Geprüft am',
        'output' => 'Ausgabe',
        'tokens' => 'Token',
        'kind' => 'Art',
        'findings' => 'Befund',
        'not_listed' => 'nicht im Register',
        'watermarking' => [
            'yes' => 'ja',
            'no' => 'nein',
            'unknown' => 'unbekannt',
        ],
        'kinds' => [
            'generated' => 'erzeugt',
            'edited' => 'überarbeitet',
            'translated' => 'übersetzt',
            'corrected' => 'korrigiert',
        ],
        'gaps' => [
            'watermark-missing' => 'kein Wasserzeichen: das Modell versieht Text nicht mit Wasserzeichen',
            'watermark-unknown' => 'kein bekanntes Wasserzeichen: ob das Modell Text mit Wasserzeichen versieht, ist unbekannt',
            'metadata-missing' => 'keine Kennzeichnung in den Metadaten des Formats',
            'metadata-unsigned' => 'Kennzeichnung in Metadaten ohne Signatur',
        ],
        'very_short_text' => 'sehr kurzer Text, kein Wasserzeichen erwartet',
        'exception' => 'als Ausnahme geführt',
        'none' => 'keine Lücke',
    ],
];
