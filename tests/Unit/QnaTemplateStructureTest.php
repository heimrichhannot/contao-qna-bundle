<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class QnaTemplateStructureTest extends TestCase
{
    public function testInitialReaderMarkupIsNeutralAndPreparedAsALazyFrame(): void
    {
        $template = $this->read('contao/templates/content_element/qna_session_reader.html.twig');

        self::assertStringContainsString('<turbo-frame', $template);
        self::assertStringContainsString('loading="lazy"', $template);
        self::assertStringContainsString('view.frameId', $template);
        self::assertStringNotContainsString('REQUEST_TOKEN', $template);
        self::assertStringNotContainsString('request_token', $template);
        self::assertStringNotContainsString('hasVoted', $template);
        self::assertStringNotContainsString('statusTranslationKey', $template);
    }

    public function testQuestionPartialsAreSharedAndAccessible(): void
    {
        $readerFrame = $this->read('contao/templates/qna/reader_frame.html.twig');
        $questionList = $this->read('contao/templates/qna/question_list.html.twig');
        $question = $this->read('contao/templates/qna/question.html.twig');
        $styles = $this->read('public/qna.css');

        self::assertStringContainsString('@Contao/qna/question_list.html.twig', $readerFrame);
        self::assertStringContainsString('@Contao/qna/question.html.twig', $questionList);
        self::assertStringContainsString('class="qna-questions"', $questionList);
        self::assertStringContainsString('id="{{ frame_id }}"', $questionList);
        self::assertStringNotContainsString('<turbo-frame', $questionList);
        self::assertStringNotContainsString('aria-live', $questionList);
        self::assertStringContainsString('<button', $question);
        self::assertStringContainsString('aria-pressed=', $question);
        self::assertStringContainsString('qna-vote-button--selected', $question);
        self::assertStringContainsString(':focus-visible', $styles);
    }

    public function testParameterizedContaoTranslationsUsePositionalPlaceholders(): void
    {
        foreach (['de', 'en'] as $locale) {
            $translations = require \dirname(__DIR__, 2).'/translations/contao_default.'.$locale.'.php';
            self::assertIsArray($translations);

            foreach ([
                'qna.session_list.open',
                'qna.question.vote_count',
                'qna.vote.label',
                'qna.vote.selected_label',
            ] as $key) {
                self::assertIsString($translations[$key] ?? null);
                self::assertStringContainsString('%s', $translations[$key]);
                self::assertDoesNotMatchRegularExpression('/%[a-z_]+%/i', $translations[$key]);
            }
        }

        self::assertStringContainsString(
            "trans([session.title], 'contao_default')",
            $this->read('contao/templates/content_element/qna_session_list.html.twig'),
        );
        self::assertStringContainsString(
            "trans([question.voteCount], 'contao_default')",
            $this->read('contao/templates/qna/question.html.twig'),
        );
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(\dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($contents);

        return $contents;
    }
}
