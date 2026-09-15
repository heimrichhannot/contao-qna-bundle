<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class QnaTurboAssetTest extends TestCase
{
    public function testTurboComesFromTheSharedEncoreEntryInsteadOfAVendoredCopy(): void
    {
        $polling = $this->read('assets/js/qna.js');

        // Turbo comes from an entry the project activates; the bundle must not
        // impose the Drive setting by pulling in a specific entry itself.
        self::assertStringContainsString('const Turbo = window.Turbo', $polling);
        self::assertStringContainsString('console.error', $polling);
        self::assertStringNotContainsString('await import(', $polling);
        self::assertStringNotContainsString('Turbo.session.drive', $polling);
        self::assertStringNotContainsString("import * as Turbo from '@hotwired/turbo'", $polling);
        self::assertStringContainsString('import "../css/qna.css"', $polling);
        self::assertStringNotContainsString('2500', $polling);

        self::assertFileDoesNotExist(\dirname(__DIR__, 2).'/public');
    }

    public function testTheBundleDoesNotActivateATurboEntryItself(): void
    {
        // Forcing huh_ux_turbo_encore_no_drive would switch Turbo Drive off for
        // the whole project. That choice belongs to the project, not to us.
        foreach ([
            'src/Controller/Page/QnaStageController.php',
            'src/Controller/ContentElement/QnaSessionReaderController.php',
            'src/Controller/ContentElement/QnaSessionListController.php',
        ] as $path) {
            self::assertStringNotContainsString('huh_ux_turbo_encore', $this->read($path));
            self::assertStringNotContainsString('NO_DRIVER', $this->read($path));
        }
    }

    public function testPollingImplementsVisibilityBackoffAndCleanup(): void
    {
        $polling = $this->read('assets/js/qna.js');

        self::assertStringContainsString('document.hidden', $polling);
        self::assertStringContainsString('BACKOFF_FACTOR ** state.failures', $polling);
        self::assertStringContainsString('turbo:fetch-request-error', $polling);
        self::assertStringContainsString('turbo:frame-missing', $polling);
        self::assertStringContainsString('turbo:before-cache', $polling);
        self::assertStringContainsString('new MutationObserver', $polling);
        self::assertStringContainsString('frames.has(frame)', $polling);
        self::assertStringContainsString('frame.hasAttribute("src")', $polling);
        self::assertStringContainsString('frame.matches(":focus-within")', $polling);
        self::assertStringContainsString('frame.hasAttribute("busy")', $polling);
        self::assertStringContainsString('turbo:before-frame-render', $polling);
        self::assertStringContainsString('Turbo.morphTurboFrameElements', $polling);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(\dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($contents);

        return $contents;
    }
}
