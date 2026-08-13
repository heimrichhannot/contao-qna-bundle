<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\View\QnaReaderViewFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QnaReaderViewFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{SessionState, bool, bool, bool, string}>
     */
    public static function stateProvider(): iterable
    {
        yield 'waiting' => [SessionState::WAITING, false, false, false, 'qna.reader.status.waiting'];
        yield 'open' => [SessionState::OPEN, true, true, true, 'qna.reader.status.open'];
        yield 'closed' => [SessionState::CLOSED, false, true, false, 'qna.reader.status.closed'];
    }

    #[DataProvider('stateProvider')]
    public function testDynamicViewContainsPrecomputedStateBehavior(
        SessionState $state,
        bool $showQuestionForm,
        bool $showQuestions,
        bool $showVoteButtons,
        string $statusTranslationKey,
    ): void {
        $session = new QnaSession(42, 'Future', 'future', true, $state, null, null);
        $view = (new QnaReaderViewFactory())->createDynamic($session);

        self::assertSame('qna-session-42-reader', $view->frameId);
        self::assertSame('qna-session-42-questions', $view->questionsFrameId);
        self::assertSame($state->value, $view->state);
        self::assertSame($statusTranslationKey, $view->statusTranslationKey);
        self::assertSame($showQuestionForm, $view->showQuestionForm);
        self::assertSame($showQuestions, $view->showQuestions);
        self::assertSame($showVoteButtons, $view->showVoteButtons);
    }

    public function testInitialViewOnlyContainsCacheNeutralData(): void
    {
        $session = new QnaSession(42, 'Future', 'future', true, SessionState::OPEN, 100, null);
        $view = (new QnaReaderViewFactory())->createInitial($session);

        self::assertSame(
            ['sessionId' => 42, 'title' => 'Future', 'frameId' => 'qna-session-42-reader'],
            get_object_vars($view),
        );
    }

    public function testAnonymousOpenReaderShowsQuestionsWithoutWriteControls(): void
    {
        $session = new QnaSession(42, 'Future', 'future', true, SessionState::OPEN, 100, null);

        $view = (new QnaReaderViewFactory())->createDynamic($session, false);

        self::assertTrue($view->showQuestions);
        self::assertFalse($view->showQuestionForm);
        self::assertFalse($view->showVoteButtons);
    }
}
