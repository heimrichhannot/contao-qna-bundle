<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ContentElementDcaTest extends TestCase
{
    public function testListOnlyConfiguresTheReaderPageAndReaderHasNoSessionSelection(): void
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
            '{type_legend},type',
            $contentDca['palettes']['qna_session_reader'],
        );
        self::assertStringNotContainsString('session', $contentDca['palettes']['qna_session_reader']);
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
