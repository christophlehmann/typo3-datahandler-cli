<?php

$EM_CONF['datahandler_cli'] = [
    'title' => 'DataHandler CLI',
    'description' => 'Use CLI commands to modify database records with the TYPO3 DataHandler. A lowlevel way for mass changes.',
    'category' => 'misc',
    'version' => '0.0.1-dev',
    'state' => 'stable',
    'author' => 'Christoph Lehmann',
    'author_email' => 'post@christophlehmann.eu',
    'constraints' => [
        'depends' => [
            'typo3' => '*'
        ],
    ]
];
