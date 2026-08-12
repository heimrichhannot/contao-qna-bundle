<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_qna_question'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_qna_session',
        'ctable' => ['tl_qna_vote'],
        'enableVersioning' => true,
        'notCreatable' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notSortable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'createdAt' => 'index',
                'pid,createdAt' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_PARENT,
            'fields' => ['createdAt DESC'],
            'panelLayout' => 'search,limit',
            'defaultSearchField' => 'question',
            'headerFields' => ['title', 'alias', 'published', 'state', 'startedAt', 'endedAt'],
        ],
        'label' => [
            'fields' => ['question'],
            'format' => '%s',
        ],
        'global_operations' => [],
        'operations' => ['delete', 'show'],
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true],
        ],
        'tstamp' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'pid' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'memberId' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'question' => [
            'search' => true,
            'sql' => ['type' => 'text'],
        ],
        'createdAt' => [
            'eval' => ['rgxp' => 'datim'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
    ],
];
