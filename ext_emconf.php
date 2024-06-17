<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'SlimPHP - TYPO3 Bridge',
    'description' => 'Provides an integration for SlimPHP for API calls in Frontend.',
    'category' => 'fe',
    'state' => 'stable',
    'author' => 'b13',
    'author_email' => 'typo3@b13.com',
    'author_company' => 'b13 GmbH',
    'version' => '0.6.1',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-12.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
