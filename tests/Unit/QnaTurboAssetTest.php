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
        self::assertStringContainsString('let Turbo = window.Turbo', $polling);
        self::assertStringContainsString('if (!Turbo)', $polling);
        self::assertStringContainsString(
            'await import("./turbo.es2017-esm.js?v=b9d35d123a07")',
            $polling,
        );
        self::assertStringContainsString('Turbo.session.drive = false', $polling);
        self::assertStringNotContainsString('import * as Turbo', $polling);
        self::assertStringNotContainsString('2500', $polling);
    }

    public function testEveryPublicAssetHasACacheBustingManifestEntry(): void
    {
        $manifest = json_decode($this->read('public/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);

        foreach (['qna.css', 'qna.js', 'turbo.es2017-esm.js'] as $asset) {
            $hash = substr(hash('sha256', $this->read('public/'.$asset)), 0, 12);

            self::assertSame($asset.'?v='.$hash, $manifest[$asset] ?? null);
        }
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
        self::assertStringContainsString('frame.matches(":focus-within")', $polling);
        self::assertStringContainsString('frame.hasAttribute("busy")', $polling);
        self::assertStringContainsString('turbo:before-frame-render', $polling);
        self::assertStringContainsString('Turbo.morphTurboFrameElements', $polling);
    }

    public function testEveryPollingTemplateFrameHasASource(): void
    {
        $reader = $this->read('contao/templates/content_element/qna_session_reader.html.twig');
        self::assertSame(2, substr_count($reader, '<turbo-frame'));
        self::assertSame(1, preg_match_all('/\bdata-qna-poll(?=\s|>)/', $reader));
        self::assertMatchesRegularExpression(
            '/<turbo-frame(?=[^>]*src="{{ questions_frame_src }}")(?=[^>]*refresh="morph")(?=[^>]*data-qna-poll(?:\\s|>))[^>]*>/s',
            $reader,
        );

        $stage = $this->read('contao/templates/qna/stage_detail.html.twig');
        self::assertSame(1, substr_count($stage, '<turbo-frame'));
        self::assertSame(1, preg_match_all('/\bdata-qna-poll(?=\s|>)/', $stage));
        self::assertMatchesRegularExpression(
            '/<turbo-frame(?=[^>]*src="{{ frame_src }}")(?=[^>]*refresh="morph")(?=[^>]*data-qna-poll(?:\\s|>))[^>]*>/s',
            $stage,
        );
    }

    public function testFrameResponsesDoNotReferenceTheirOwnSourceUrl(): void
    {
        foreach ([
            'contao/templates/qna/reader_controls_frame.html.twig',
            'contao/templates/qna/reader_questions_frame.html.twig',
            'contao/templates/qna/stage_questions.html.twig',
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
