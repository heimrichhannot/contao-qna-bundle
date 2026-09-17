<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_content']['palettes']['qna_session_list'] =
    '{type_legend},type,headline,title;{qna_legend},jumpTo;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['palettes']['qna_session_reader'] =
    '{type_legend},type,headline,title;{qna_legend},qnaSession;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['fields']['qnaSession'] = [
    'inputType' => 'select',
    'foreignKey' => 'tl_qna_session.title',
    'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];
