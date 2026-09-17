<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use HeimrichHannot\QnaBundle\Domain\Session;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\InvalidSessionTransitionException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Service\SessionService;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class SessionServiceTest extends TestCase
{
    public function testStartOpensWaitingSessionAndSetsStartedAt(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->with(12, true)->willReturn($this->session(SessionState::WAITING));
        $gateway->expects(self::once())->method('markOpen')->with(12, 1_700_000_000)->willReturn(true);

        $session = (new SessionService($gateway, $this->clock(), $this->connection()))->start(12);

        self::assertSame(SessionState::OPEN, $session->state);
        self::assertSame(1_700_000_000, $session->startedAt);
        self::assertNull($session->endedAt);
    }

    public function testStopClosesOpenSessionAndSetsEndedAt(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->with(12, true)->willReturn($this->session(SessionState::OPEN));
        $gateway->expects(self::once())->method('markClosed')->with(12, 1_700_000_000)->willReturn(true);

        $session = (new SessionService($gateway, $this->clock(), $this->connection()))->stop(12);

        self::assertSame(SessionState::CLOSED, $session->state);
        self::assertSame(1_700_000_000, $session->endedAt);
    }

    public function testClosedSessionCannotBeStartedAgain(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->willReturn($this->session(SessionState::CLOSED));
        $gateway->expects(self::never())->method('markOpen');

        $this->expectException(InvalidSessionTransitionException::class);

        (new SessionService($gateway, $this->clock(), $this->connection()))->start(12);
    }

    public function testUnpublishedSessionCannotBeStarted(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->willReturn($this->session(SessionState::WAITING, false));
        $gateway->expects(self::never())->method('markOpen');

        $this->expectException(SessionNotPublishedException::class);

        (new SessionService($gateway, $this->clock(), $this->connection()))->start(12);
    }

    public function testRestartOpensNextRoundAndClearsEndTimestamp(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->with(12, true)
            ->willReturn(new Session(12, 'Session', 'session', true, SessionState::CLOSED, 100, 200, 3));
        $gateway->expects(self::once())->method('markReopened')->with(12, 1_700_000_000)->willReturn(true);
        $session = (new SessionService($gateway, $this->clock(), $this->connection()))->restart(12);
        self::assertSame(SessionState::OPEN, $session->state);
        self::assertSame(4, $session->round);
        self::assertSame(1_700_000_000, $session->startedAt);
        self::assertNull($session->endedAt);
        self::assertSame(4, $session->withState(SessionState::CLOSED, 1_700_000_001)->round);
    }

    /** @return iterable<string, array{SessionState, bool, class-string<\Throwable>}> */
    public static function rejectedRestarts(): iterable
    {
        yield 'waiting' => [SessionState::WAITING, true, InvalidSessionTransitionException::class];
        yield 'open' => [SessionState::OPEN, true, InvalidSessionTransitionException::class];
        yield 'unpublished' => [SessionState::CLOSED, false, SessionNotPublishedException::class];
    }

    /** @param class-string<\Throwable> $exception */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedRestarts')]
    public function testRejectedRestartNeverWrites(SessionState $state, bool $published, string $exception): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->with(12, true)->willReturn($this->session($state, $published));
        $gateway->expects(self::never())->method('markReopened');
        $this->expectException($exception);
        (new SessionService($gateway, $this->clock(), $this->connection()))->restart(12);
    }

    public function testFailedRestartRereadsLockedSession(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::exactly(2))->method('find')->with(12, true)
            ->willReturn($this->session(SessionState::CLOSED), $this->session(SessionState::OPEN));
        $gateway->expects(self::once())->method('markReopened')->willReturn(false);
        $this->expectException(InvalidSessionTransitionException::class);
        (new SessionService($gateway, $this->clock(), $this->connection()))->restart(12);
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        $connection = $this->createStub(\Doctrine\DBAL\Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $callback): mixed => $callback());

        return $connection;
    }

    private function session(SessionState $state, bool $published = true): Session
    {
        return new Session(12, 'Session', 'session', $published, $state, null, null);
    }

    private function clock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@1700000000');
            }
        };
    }
}
