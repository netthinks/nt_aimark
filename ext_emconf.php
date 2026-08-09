<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'AI Mark – KI-Kennzeichnung (EU AI Act)',
    'description' => 'Erfassung, Kennzeichnung und Nachweis KI-generierter Inhalte nach Art. 50 der EU-KI-Verordnung. Barrierefreie Frontend-Labels mit den offiziellen EU-Icons, Auslesen von C2PA-/IPTC-Provenienzdaten, Erhalt der Metadaten über die Bildverarbeitung hinweg und ein Backend-Modul zur Nachweisführung.',
    'category' => 'module',
    'author' => 'Dietmar Engler',
    'author_email' => 'info@netthinks.com',
    'author_company' => 'NET.THINKS',
    'state' => 'beta',
    'version' => '0.9.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.4.99',
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
