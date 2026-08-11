<?php

declare(strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_qna_session'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ctable' => ['tl_qna_question'],
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'alias' => 'unique',
                'published' => 'index',
            ],
        ],
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true],
        ],
        'tstamp' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'title' => [
            'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
        ],
        'alias' => [
            'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
        ],
        'published' => [
            'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => ''],
        ],
        'state' => [
            'sql' => ['type' => 'string', 'length' => 16, 'default' => 'waiting'],
        ],
        'startedAt' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'notnull' => false, 'default' => null],
        ],
        'endedAt' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'notnull' => false, 'default' => null],
        ],
    ],
];
