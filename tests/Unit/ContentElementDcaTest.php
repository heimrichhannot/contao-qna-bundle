<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ContentElementDcaTest extends TestCase
{
    public function testReaderOffersAnOptionalSessionAndNoReaderPage(): void
    {
        if (!isset($GLOBALS['TL_DCA']) || !\is_array($GLOBALS['TL_DCA'])) {
            $GLOBALS['TL_DCA'] = [];
        }

        $dataContainers = $GLOBALS['TL_DCA'];
        $dataContainers['tl_content'] = [];
        $GLOBALS['TL_DCA'] = $dataContainers;

        require \dirname(__DIR__, 2).'/contao/dca/tl_content.php';

        $dataContainers = $GLOBALS['TL_DCA'];
        self::assertIsArray($dataContainers);
        $contentDca = $dataContainers['tl_content'];
        self::assertIsArray($contentDca);
        self::assertIsArray($contentDca['palettes']);

        self::assertSame(
            '{type_legend},type;{qna_legend},jumpTo',
            $contentDca['palettes']['qna_session_list'],
        );
        self::assertSame(
            '{type_legend},type;{qna_legend},qnaSession',
            $contentDca['palettes']['qna_session_reader'],
        );
        self::assertIsArray($contentDca['fields']);
        $field = $contentDca['fields']['qnaSession'];
        self::assertIsArray($field);
        self::assertSame(['type' => 'integer', 'unsigned' => true, 'default' => 0], $field['sql']);
        self::assertSame(['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'], $field['eval']);
        self::assertSame('tl_qna_session.title', $field['foreignKey']);
        self::assertSame('select', $field['inputType']);
        self::assertSame(['type' => 'hasOne', 'load' => 'lazy'], $field['relation']);
        self::assertStringNotContainsString('jumpTo', $contentDca['palettes']['qna_session_reader']);
    }

    public function testGermanAndEnglishElementNamesAndCategoryAreTranslated(): void
    {
        foreach (['de', 'en'] as $locale) {
            $translations = require \dirname(__DIR__, 2).'/translations/contao_default.'.$locale.'.php';
            self::assertIsArray($translations);
            self::assertArrayHasKey('CTE.qna', $translations);
            self::assertArrayHasKey('CTE.qna_session_list.0', $translations);
            self::assertArrayHasKey('CTE.qna_session_reader.0', $translations);
        }
    }
}
