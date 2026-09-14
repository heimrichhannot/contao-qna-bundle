<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use HeimrichHannot\QnaBundle\Domain\QuestionListItem;
use HeimrichHannot\QnaBundle\Tests\Fixtures\TemplateEnvironment;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class QnaTemplateStructureTest extends TestCase
{
    public function testInitialReaderMarkupIsNeutralAndPreparedAsSeparateLazyFrames(): void
    {
        $context = $this->readerContext();
        $context['as_editor_view'] = false;
        $context['controls_frame_src'] = '/controls';
        $context['questions_frame_src'] = '/questions';
        $context['polling_max_interval'] = 40000;
        $html = $this->twig()->render('@Contao/content_element/qna_session_reader.html.twig', $context);
        $dom = $this->dom($html);

        self::assertSame(2, $dom->getElementsByTagName('turbo-frame')->length);
        $controls = $dom->getElementById('reader');
        $questions = $dom->getElementById('questions');
        self::assertInstanceOf(\DOMElement::class, $controls);
        self::assertInstanceOf(\DOMElement::class, $questions);
        self::assertSame('/controls', $controls->getAttribute('src'));
        self::assertSame('/questions', $questions->getAttribute('src'));
        self::assertSame('lazy', $controls->getAttribute('loading'));
        self::assertSame('lazy', $questions->getAttribute('loading'));
        self::assertFalse($controls->hasAttribute('data-qna-poll'));
        self::assertTrue($questions->hasAttribute('data-qna-poll'));
        self::assertSame(0, $dom->getElementsByTagName('form')->length);
        self::assertSame(0, $dom->getElementsByTagName('input')->length);
        self::assertStringNotContainsString('REQUEST_TOKEN', $html);
        self::assertStringNotContainsString('secret-token', $html);
        self::assertStringNotContainsString('private-member-status', $html);
        self::assertStringNotContainsString('Private member question', $html);
        self::assertStringNotContainsString('aria-pressed', $html);
        self::assertStringNotContainsString('qna-vote-button--selected', $html);
        // A different member/status/token must produce byte-identical initial markup.
        $context['request_token'] = 'another-token';
        $context['view']['state'] = 'closed';
        $context['view']['statusTranslationKey'] = 'another-private-status';
        $context['view']['showQuestionForm'] = false;
        $context['view']['showVoteButtons'] = false;
        $context['questions'] = [];
        self::assertSame($html, $this->twig()->render('@Contao/content_element/qna_session_reader.html.twig', $context));
    }

    public function testQuestionPartialsRenderAccessibleSelectedVote(): void
    {
        $html = $this->twig()->render('@Contao/qna/reader_questions.html.twig', $this->readerContext());
        $dom = $this->dom($html);
        self::assertSame(0, $dom->getElementsByTagName('turbo-frame')->length);
        $list = $dom->getElementById('questions-list');
        self::assertInstanceOf(\DOMElement::class, $list);
        self::assertSame('qna-questions', $list->getAttribute('class'));
        self::assertFalse($list->hasAttribute('aria-live'));
        $button = $dom->getElementById('qna-question-23-vote');
        self::assertInstanceOf(\DOMElement::class, $button);
        self::assertSame('button', $button->tagName);
        self::assertSame('true', $button->getAttribute('aria-pressed'));
        self::assertStringContainsString('qna-vote-button--selected', $button->getAttribute('class'));
        self::assertStringContainsString('Private member question', $button->getAttribute('aria-label'));
        self::assertStringContainsString('secret-token', $html);
    }

    public function testQuestionFormAndPollingListUseSeparateFrames(): void
    {
        $twig = $this->twig();
        $context = $this->readerContext();
        $controls = $this->dom($twig->render('@Contao/qna/reader_controls_frame.html.twig', $context));
        $form = $controls->getElementsByTagName('form')->item(0);
        self::assertInstanceOf(\DOMElement::class, $form);
        self::assertSame('questions', $form->getAttribute('data-turbo-frame'));
        self::assertSame('reader', $controls->getElementsByTagName('turbo-frame')->item(0)?->getAttribute('id'));
        $questions = $this->dom($twig->render('@Contao/qna/reader_questions_frame.html.twig', $context));
        self::assertSame('questions', $questions->getElementsByTagName('turbo-frame')->item(0)?->getAttribute('id'));
        $update = $questions->getElementsByTagName('turbo-stream')->item(0);
        self::assertSame("#controls:not([data-qna-state='open'])", $update?->getAttribute('targets'));
        $context['reset_question_form'] = true;
        $reset = $this->dom($twig->render('@Contao/qna/reader_controls_update.html.twig', $context));
        self::assertSame('controls', $reset->getElementsByTagName('turbo-stream')->item(0)?->getAttribute('target'));
    }

    public function testParameterizedContaoTranslationsUsePositionalPlaceholders(): void
    {
        foreach (['de', 'en'] as $locale) {
            $translations = require \dirname(__DIR__, 2).'/translations/contao_default.'.$locale.'.php';
            self::assertIsArray($translations);
            foreach (['qna.session_list.open', 'qna.question.vote_count', 'qna.vote.label', 'qna.vote.selected_label'] as $key) {
                self::assertIsString($translations[$key] ?? null);
                self::assertStringContainsString('%s', $translations[$key]);
                self::assertDoesNotMatchRegularExpression('/%[a-z_]+%/i', $translations[$key]);
            }
        }
    }

    /** @return array{view: array<string, mixed>, questions: list<QuestionListItem>, request_token: string, vote_urls: array<int, string>, error_translation_key: null, polling_interval: int, max_question_length: int, question_value: string, question_form_action: string} */
    private function readerContext(): array
    {
        return [
            'view' => ['sessionId' => 7, 'title' => 'Public session', 'frameId' => 'reader', 'questionsFrameId' => 'questions', 'controlsContentId' => 'controls', 'state' => 'open', 'statusTranslationKey' => 'private-member-status', 'showQuestionForm' => true, 'showQuestions' => true, 'showVoteButtons' => true],
            'questions' => [new QuestionListItem(23, 7, 42, 'Private member question', 100, 3, true)],
            'request_token' => 'secret-token',
            'vote_urls' => [23 => '/vote'],
            'error_translation_key' => null,
            'polling_interval' => 2500,
            'max_question_length' => 500,
            'question_value' => '',
            'question_form_action' => '/submit',
        ];
    }

    private function twig(): Environment
    {
        return TemplateEnvironment::create();
    }

    private function dom(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            self::assertTrue($document->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }
}
