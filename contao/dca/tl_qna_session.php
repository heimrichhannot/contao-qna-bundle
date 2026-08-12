<?php

declare(strict_types=1);

use Contao\DataContainer;
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
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['title'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'search,filter,limit',
            'defaultSearchField' => 'title',
        ],
        'label' => [
            'fields' => ['title', 'alias'],
            'format' => '%s <span class="label-info">[%s]</span>',
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,alias;{publish_legend},published',
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true],
        ],
        'tstamp' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'title' => [
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
        ],
        'alias' => [
            'search' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'alias', 'doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
        ],
        'published' => [
            'toggle' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => ''],
        ],
        'state' => [
            'reference' => &$GLOBALS['TL_LANG']['tl_qna_session']['stateOptions'],
            'sql' => ['type' => 'string', 'length' => 16, 'default' => 'waiting'],
        ],
        'startedAt' => [
            'eval' => ['rgxp' => 'datim'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'notnull' => false, 'default' => null],
        ],
        'endedAt' => [
            'eval' => ['rgxp' => 'datim'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'notnull' => false, 'default' => null],
        ],
    ],
];
