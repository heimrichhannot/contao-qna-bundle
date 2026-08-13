<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class QnaTurboAssetTest extends TestCase
{
    public function testTurboIsPinnedAndAnExistingInstanceIsKeptUnchanged(): void
    {
        $turbo = $this->read('public/turbo.es2017-esm.js');
        $polling = $this->read('public/qna.js');

        self::assertStringStartsWith("/*!\nTurbo 8.0.23", $turbo);
        self::assertStringContainsString('if (!window.Turbo)', $polling);
        self::assertStringContainsString('await import("./turbo.es2017-esm.js")', $polling);
        self::assertStringContainsString('Turbo.session.drive = false', $polling);
        self::assertStringNotContainsString('import * as Turbo', $polling);
        self::assertStringNotContainsString('2500', $polling);
    }

    public function testPollingImplementsVisibilityBackoffAndCleanup(): void
    {
        $polling = $this->read('public/qna.js');

        self::assertStringContainsString('document.hidden', $polling);
        self::assertStringContainsString('BACKOFF_FACTOR ** state.failures', $polling);
        self::assertStringContainsString('turbo:fetch-request-error', $polling);
        self::assertStringContainsString('turbo:frame-missing', $polling);
        self::assertStringContainsString('turbo:before-cache', $polling);
        self::assertStringContainsString('new MutationObserver', $polling);
        self::assertStringContainsString('frames.has(frame)', $polling);
        self::assertStringContainsString('frame.hasAttribute("src")', $polling);
    }

    public function testEveryPollingTemplateFrameHasASource(): void
    {
        foreach ([
            'templates/content_element/qna_session_reader.html.twig',
            'templates/qna/stage_detail.html.twig',
        ] as $path) {
            $template = $this->read($path);
            self::assertMatchesRegularExpression(
                '/<turbo-frame(?=[^>]*src="{{ frame_src }}")(?=[^>]*data-qna-poll(?:\\s|>))[^>]*>/s',
                $template,
            );
        }
    }

    public function testFrameResponsesDoNotReferenceTheirOwnSourceUrl(): void
    {
        foreach ([
            'templates/qna/reader_frame.html.twig',
            'templates/qna/stage_questions.html.twig',
        ] as $path) {
            $template = $this->read($path);
            self::assertSame(1, preg_match('/<turbo-frame\b[^>]*>/s', $template, $matches));
            $openingTag = $matches[0] ?? null;
            self::assertIsString($openingTag);
            self::assertStringNotContainsString('src=', $openingTag);
            self::assertStringNotContainsString('data-qna-poll', $openingTag);
        }
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(\dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($contents);

        return $contents;
    }
}
