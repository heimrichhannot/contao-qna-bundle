<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class QnaTemplateStructureTest extends TestCase
{
    public function testInitialReaderMarkupIsNeutralAndPreparedAsALazyFrame(): void
    {
        $template = $this->read('contao/templates/content_element/qna_session_reader.html.twig');

        self::assertSame(2, substr_count($template, '<turbo-frame'));
        self::assertStringContainsString('loading="lazy"', $template);
        self::assertStringContainsString('view.frameId', $template);
        self::assertStringContainsString('view.questionsFrameId', $template);
        self::assertStringNotContainsString('REQUEST_TOKEN', $template);
        self::assertStringNotContainsString('request_token', $template);
        self::assertStringNotContainsString('hasVoted', $template);
        self::assertStringNotContainsString('statusTranslationKey', $template);
    }

    public function testQuestionPartialsAreSharedAndAccessible(): void
    {
        $readerQuestions = $this->read('contao/templates/qna/reader_questions.html.twig');
        $questionList = $this->read('contao/templates/qna/question_list.html.twig');
        $question = $this->read('contao/templates/qna/question.html.twig');
        $styles = $this->read('public/qna.css');

        self::assertStringContainsString('@Contao/qna/question_list.html.twig', $readerQuestions);
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

    public function testQuestionFormAndPollingListUseSeparateFrames(): void
    {
        $shell = $this->read('contao/templates/content_element/qna_session_reader.html.twig');
        $controls = $this->read('contao/templates/qna/reader_controls.html.twig');
        $questions = $this->read('contao/templates/qna/reader_questions_frame.html.twig');
        $controlsUpdate = $this->read('contao/templates/qna/reader_controls_update.html.twig');

        self::assertMatchesRegularExpression(
            '/<turbo-frame(?=[^>]*id="{{ view.frameId }}")(?=[^>]*src="{{ controls_frame_src }}")[^>]*>/s',
            $shell,
        );
        self::assertMatchesRegularExpression(
            '/<turbo-frame(?=[^>]*id="{{ view.questionsFrameId }}")(?=[^>]*src="{{ questions_frame_src }}")(?=[^>]*data-qna-poll(?:\\s|>))[^>]*>/s',
            $shell,
        );
        self::assertSame(
            1,
            preg_match('/<turbo-frame(?=[^>]*id="{{ view.frameId }}")[^>]*>/s', $shell, $controlsFrame),
        );
        $controlsOpeningTag = $controlsFrame[0] ?? null;
        self::assertIsString($controlsOpeningTag);
        self::assertStringNotContainsString('data-qna-poll', $controlsOpeningTag);
        self::assertStringContainsString('class="qna-question-form"', $controls);
        self::assertStringContainsString('data-turbo-frame="{{ view.questionsFrameId }}"', $controls);
        self::assertStringNotContainsString('qna-question-form', $questions);
        self::assertStringContainsString("data-qna-state='{{ view.state }}'", $controlsUpdate);
        self::assertStringContainsString('reset_question_form', $controlsUpdate);
    }

    public function testStageSortLinksKeepStableIdsAndUseFrameNavigation(): void
    {
        $stage = $this->read('contao/templates/qna/stage_content.html.twig');

        self::assertStringContainsString('id="qna-session-{{ session.id }}-sort-votes"', $stage);
        self::assertStringContainsString('id="qna-session-{{ session.id }}-sort-time"', $stage);
        self::assertStringNotContainsString('data-turbo-stream', $stage);
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
