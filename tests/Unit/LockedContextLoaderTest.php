<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use HeimrichHannot\QnaBundle\Dto\QnaQuestion;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\QuestionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Gateway\LockedContextLoader;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LockedContextLoaderTest extends TestCase
{
    /** @return iterable<string, array{?QnaSession, ?QnaQuestion, class-string<\Throwable>}> */
    public static function rejectionPrecedence(): iterable
    {
        yield 'missing question takes precedence over missing session' => [
            null,
            null,
            QuestionNotFoundException::class,
        ];
        yield 'question from another session takes precedence' => [
            self::session(),
            new QnaQuestion(23, 8, 1, 'Question', 100),
            QuestionNotFoundException::class,
        ];
        yield 'closed session is rejected after question validation' => [
            self::session(SessionState::CLOSED),
            new QnaQuestion(23, 7, 1, 'Question', 100),
            SessionNotOpenException::class,
        ];
    }

    /** @param class-string<\Throwable> $exception */
    #[DataProvider('rejectionPrecedence')]
    public function testLockOrderAndErrorPrecedence(
        ?QnaSession $session,
        ?QnaQuestion $question,
        string $exception,
    ): void {
        $calls = [];
        $sessions = $this->createMock(QnaSessionGateway::class);
        $sessions->expects(self::once())->method('find')->with(7, true)->willReturnCallback(
            static function () use (&$calls, $session): ?QnaSession {
                $calls[] = 'session';

                return $session;
            },
        );
        $questions = $this->createMock(QnaQuestionGateway::class);
        $questions->expects(self::once())->method('find')->with(23, true)->willReturnCallback(
            static function () use (&$calls, $question): ?QnaQuestion {
                $calls[] = 'question';

                return $question;
            },
        );

        try {
            (new LockedContextLoader($sessions, $questions))->lockOpenSessionWithQuestion(7, 23);
            self::fail('Expected the locked context to be rejected.');
        } catch (\Throwable $caught) {
            self::assertInstanceOf($exception, $caught);
            self::assertSame(['session', 'question'], $calls);
        }
    }

    private static function session(SessionState $state = SessionState::OPEN): QnaSession
    {
        return new QnaSession(7, 'Session', 'session', true, $state, 100, null);
    }
}
