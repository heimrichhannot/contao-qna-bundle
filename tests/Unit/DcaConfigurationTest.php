<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DcaConfigurationTest extends TestCase
{
    public function testNewSessionDefaultsToWaitingAndOnlyEditorialFieldsAreInPalette(): void
    {
        $dca = $this->loadDca('tl_qna_session');
        $fields = $dca['fields'] ?? null;
        $palettes = $dca['palettes'] ?? null;
        $config = $dca['config'] ?? null;
        self::assertIsArray($fields);
        self::assertIsArray($palettes);
        self::assertIsArray($config);
        self::assertIsArray($fields['state']);
        self::assertIsArray($fields['state']['sql']);
        self::assertIsArray($fields['published']);
        self::assertIsArray($config['sql']);
        self::assertIsArray($config['sql']['keys']);

        self::assertSame('waiting', $fields['state']['sql']['default']);
        self::assertSame('{title_legend},title,alias;{publish_legend},published', $palettes['default']);
        self::assertTrue($fields['published']['toggle']);
        self::assertSame('unique', $config['sql']['keys']['alias']);
    }

    public function testQuestionsAreReadOnlyExceptForShowAndDelete(): void
    {
        $dca = $this->loadDca('tl_qna_question');
        $config = $dca['config'] ?? null;
        $list = $dca['list'] ?? null;
        self::assertIsArray($config);
        self::assertIsArray($list);

        self::assertTrue($config['notCreatable']);
        self::assertTrue($config['notEditable']);
        self::assertSame(['delete', 'show'], $list['operations']);
        self::assertSame([], $list['global_operations']);
    }

    public function testVoteUniquenessIsEnforcedBySchema(): void
    {
        $dca = $this->loadDca('tl_qna_vote');
        $config = $dca['config'] ?? null;
        self::assertIsArray($config);
        self::assertIsArray($config['sql']);
        self::assertIsArray($config['sql']['keys']);

        self::assertSame('unique', $config['sql']['keys']['pid,memberId']);
    }

    /**
     * @return array<mixed>
     */
    private function loadDca(string $table): array
    {
        if (!isset($GLOBALS['TL_DCA']) || !\is_array($GLOBALS['TL_DCA'])) {
            $GLOBALS['TL_DCA'] = [];
        }

        $dataContainers = $GLOBALS['TL_DCA'];
        unset($dataContainers[$table]);
        $GLOBALS['TL_DCA'] = $dataContainers;
        require \dirname(__DIR__, 2).'/contao/dca/'.$table.'.php';

        $dataContainers = $GLOBALS['TL_DCA'];
        self::assertIsArray($dataContainers);
        $dca = $dataContainers[$table] ?? null;
        self::assertIsArray($dca);

        return $dca;
    }
}
