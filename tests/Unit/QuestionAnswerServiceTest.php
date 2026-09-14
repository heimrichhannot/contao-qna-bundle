<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\QuestionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\LockedContextLoader;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Model\Question;
use HeimrichHannot\QnaBundle\Model\Session;
use HeimrichHannot\QnaBundle\Service\QuestionAnswerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuestionAnswerServiceTest extends TestCase
{
    /** @return iterable<string, array{bool, bool}> */
    public static function states(): iterable
    {
        yield 'mark' => [false, true];
        yield 'undo' => [true, false];
        yield 'repeat mark' => [true, true];
        yield 'repeat undo' => [false, false];
    }

    #[DataProvider('states')]
    public function testExplicitStateIsIdempotentAndQuestionIsLockedBeforeMutation(bool $before, bool $after): void
    {
        $transaction = new \stdClass();
        $transaction->active = false;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional')->willReturnCallback(
            static function (callable $callback) use ($transaction): void {
                $transaction->active = true;
                $callback();
                $transaction->active = false;
            },
        );
        $questions = $this->createMock(QnaQuestionGateway::class);
        $questions->expects(self::once())->method('find')->with(23, true)->willReturnCallback(
            static function () use ($transaction, $before): Question {
                self::assertTrue($transaction->active);

                return new Question(23, 7, 1, 'Question', 100, $before);
            },
        );
        $questions->expects($before === $after ? self::never() : self::once())->method('setAnswered')->with(23, $after);
        $sessions = $this->createStub(QnaSessionGateway::class);
        $sessions->method('find')->willReturn(new Session(7, 'Session', 'session', true, SessionState::OPEN, 100, null));

        (new QuestionAnswerService(new LockedContextLoader($sessions, $questions), $questions, $connection))->setAnswered(7, 23, $after);
    }

    /** @return iterable<string, array{?Question, ?Session, class-string<\Throwable>}> */
    public static function rejections(): iterable
    {
        $question = new Question(23, 7, 1, 'Question', 100);
        yield 'missing question' => [null, null, QuestionNotFoundException::class];
        yield 'wrong session' => [new Question(23, 8, 1, 'Question', 100), null, QuestionNotFoundException::class];
        yield 'missing session' => [$question, null, SessionNotFoundException::class];
        yield 'unpublished' => [$question, new Session(7, '', '', false, SessionState::OPEN, 100, null), SessionNotPublishedException::class];
        foreach ([SessionState::WAITING, SessionState::CLOSED] as $state) {
            yield $state->value => [$question, new Session(7, '', '', true, $state, null, null), SessionNotOpenException::class];
        }
    }

    /** @param class-string<\Throwable> $exception */
    #[DataProvider('rejections')]
    public function testRejectedChangesNeverWrite(?Question $question, ?Session $session, string $exception): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $questions = $this->createMock(QnaQuestionGateway::class);
        $questions->method('find')->willReturn($question);
        $questions->expects(self::never())->method('setAnswered');
        $sessions = $this->createStub(QnaSessionGateway::class);
        $sessions->method('find')->willReturn($session);
        $this->expectException($exception);

        (new QuestionAnswerService(new LockedContextLoader($sessions, $questions), $questions, $connection))->setAnswered(7, 23, true);
    }
}
