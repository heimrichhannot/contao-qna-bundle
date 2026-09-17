<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_content']['palettes']['qna_session_list'] =
    '{type_legend},type;{qna_legend},jumpTo';
$GLOBALS['TL_DCA']['tl_content']['palettes']['qna_session_reader'] =
    '{type_legend},type;{qna_legend},qnaSession';

$GLOBALS['TL_DCA']['tl_content']['fields']['qnaSession'] = [
    'inputType' => 'select',
    'foreignKey' => 'tl_qna_session.title',
    'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];
