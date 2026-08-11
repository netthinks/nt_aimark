<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'AI Mark – AI transparency (EU AI Act)',
    'description' => 'Record, label and document AI-generated content under Art. 50 of the EU AI Act. Accessible frontend labels with the official EU icons, C2PA and IPTC provenance detection, metadata preserved through image processing, and a backend module for the audit trail. German and English labels and documentation.',
    'category' => 'module',
    'author' => 'NET.THINKS',
    'author_email' => 'info@netthinks.com',
    'author_company' => 'NET.THINKS',
    'state' => 'beta',
    'version' => '0.9.7',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.4.99',
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
