<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use HeimrichHannot\QnaBundle\Dto\QnaSession;
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
        $gateway->expects(self::once())->method('find')->with(12)->willReturn($this->session(SessionState::WAITING));
        $gateway->expects(self::once())->method('markOpen')->with(12, 1_700_000_000)->willReturn(true);

        $session = (new SessionService($gateway, $this->clock()))->start(12);

        self::assertSame(SessionState::OPEN, $session->state);
        self::assertSame(1_700_000_000, $session->startedAt);
        self::assertNull($session->endedAt);
    }

    public function testStopClosesOpenSessionAndSetsEndedAt(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->with(12)->willReturn($this->session(SessionState::OPEN));
        $gateway->expects(self::once())->method('markClosed')->with(12, 1_700_000_000)->willReturn(true);

        $session = (new SessionService($gateway, $this->clock()))->stop(12);

        self::assertSame(SessionState::CLOSED, $session->state);
        self::assertSame(1_700_000_000, $session->endedAt);
    }

    public function testClosedSessionCannotBeStartedAgain(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->willReturn($this->session(SessionState::CLOSED));
        $gateway->expects(self::never())->method('markOpen');

        $this->expectException(InvalidSessionTransitionException::class);

        (new SessionService($gateway, $this->clock()))->start(12);
    }

    public function testUnpublishedSessionCannotBeStarted(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->willReturn($this->session(SessionState::WAITING, false));
        $gateway->expects(self::never())->method('markOpen');

        $this->expectException(SessionNotPublishedException::class);

        (new SessionService($gateway, $this->clock()))->start(12);
    }

    private function session(SessionState $state, bool $published = true): QnaSession
    {
        return new QnaSession(12, 'Session', 'session', $published, $state, null, null);
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
