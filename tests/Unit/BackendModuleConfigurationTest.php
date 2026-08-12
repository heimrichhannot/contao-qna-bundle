<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BackendModuleConfigurationTest extends TestCase
{
    public function testBundleRegistersItsOwnQnaBackendArea(): void
    {
        if (!isset($GLOBALS['BE_MOD']) || !\is_array($GLOBALS['BE_MOD'])) {
            $GLOBALS['BE_MOD'] = [];
        }

        unset($GLOBALS['BE_MOD']['qna']);
        require \dirname(__DIR__, 2).'/contao/config/config.php';

        $backendModules = $GLOBALS['BE_MOD'];
        self::assertIsArray($backendModules);
        $qnaModules = $backendModules['qna'] ?? null;
        self::assertIsArray($qnaModules);

        self::assertSame(
            ['tables' => ['tl_qna_session', 'tl_qna_question']],
            $qnaModules['qna_sessions'],
        );
    }
}
