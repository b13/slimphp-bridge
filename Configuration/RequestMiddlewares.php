<?php
return [
    'frontend' => [
        'b13/slimphp' => [
            'target' => \B13\SlimPhp\Middleware\SlimInitiator::class,
            'after' => [
                'typo3/cms-frontend/site'
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver'
            ],
        ],
    ]
];
