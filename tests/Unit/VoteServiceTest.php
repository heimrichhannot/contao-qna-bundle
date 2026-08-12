<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\FrontendUser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use HeimrichHannot\QnaBundle\Dto\QnaQuestion;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Dto\QnaVoteState;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\VoteService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class VoteServiceTest extends TestCase
{
    public function testFirstVoteIsCreatedAndReturnsCurrentState(): void
    {
        [$sessionGateway, $questionGateway, $voteGateway] = $this->gatewaysForOpenQuestion();
        $voteGateway->expects(self::once())->method('create')->with(23, 42, 1_700_000_000);
        $voteGateway->expects(self::once())->method('getState')->with(23, 42)->willReturn(new QnaVoteState(23, 3, true));

        $state = $this->service($sessionGateway, $questionGateway, $voteGateway)->vote(23);

        self::assertSame(3, $state->voteCount);
        self::assertTrue($state->hasVoted);
    }

    public function testDuplicateVoteIsIdempotentAndReturnsCurrentState(): void
    {
        [$sessionGateway, $questionGateway, $voteGateway] = $this->gatewaysForOpenQuestion();
        $voteGateway->expects(self::once())
            ->method('create')
            ->willThrowException($this->createStub(UniqueConstraintViolationException::class));
        $voteGateway->expects(self::once())->method('getState')->with(23, 42)->willReturn(new QnaVoteState(23, 3, true));

        $state = $this->service($sessionGateway, $questionGateway, $voteGateway)->vote(23);

        self::assertTrue($state->hasVoted);
        self::assertSame(3, $state->voteCount);
    }

    public function testSameMemberCanVoteForDifferentQuestions(): void
    {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session());
        $questionGateway = $this->createStub(QnaQuestionGateway::class);
        $questionGateway->method('find')->willReturnCallback(
            static fn (int $id): QnaQuestion => new QnaQuestion($id, 12, 7, 'Question', 100),
        );
        $created = [];
        $voteGateway = $this->createStub(QnaVoteGateway::class);
        $voteGateway->method('create')->willReturnCallback(
            static function (int $questionId, int $memberId) use (&$created): void {
                $created[] = [$questionId, $memberId];
            },
        );
        $voteGateway->method('getState')->willReturnCallback(
            static fn (int $questionId): QnaVoteState => new QnaVoteState($questionId, 1, true),
        );
        $service = $this->service($sessionGateway, $questionGateway, $voteGateway);

        $service->vote(23);
        $service->vote(24);

        self::assertSame([[23, 42], [24, 42]], $created);
    }

    public function testDifferentMembersCanVoteForSameQuestion(): void
    {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session());
        $questionGateway = $this->createStub(QnaQuestionGateway::class);
        $questionGateway->method('find')->willReturn(new QnaQuestion(23, 12, 7, 'Question', 100));
        $created = [];
        $voteGateway = $this->createStub(QnaVoteGateway::class);
        $voteGateway->method('create')->willReturnCallback(
            static function (int $questionId, int $memberId) use (&$created): void {
                $created[] = [$questionId, $memberId];
            },
        );
        $voteGateway->method('getState')->willReturnCallback(
            static fn (int $questionId): QnaVoteState => new QnaVoteState($questionId, 1, true),
        );

        $this->service($sessionGateway, $questionGateway, $voteGateway, 42)->vote(23);
        $this->service($sessionGateway, $questionGateway, $voteGateway, 43)->vote(23);

        self::assertSame([[23, 42], [23, 43]], $created);
    }

    public function testVoteIsRejectedUnlessSessionIsOpen(): void
    {
        [$sessionGateway, $questionGateway, $voteGateway] = $this->gatewaysForOpenQuestion(SessionState::CLOSED);
        $voteGateway->expects(self::never())->method('create');

        $this->expectException(SessionNotOpenException::class);

        $this->service($sessionGateway, $questionGateway, $voteGateway)->vote(23);
    }

    public function testVoteIsRejectedForUnpublishedSession(): void
    {
        [$sessionGateway, $questionGateway, $voteGateway] = $this->gatewaysForOpenQuestion(SessionState::OPEN, false);
        $voteGateway->expects(self::never())->method('create');

        $this->expectException(SessionNotPublishedException::class);

        $this->service($sessionGateway, $questionGateway, $voteGateway)->vote(23);
    }

    /**
     * @return array{QnaSessionGateway, QnaQuestionGateway, QnaVoteGateway&MockObject}
     */
    private function gatewaysForOpenQuestion(
        SessionState $state = SessionState::OPEN,
        bool $published = true,
    ): array {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session($state, $published));
        $questionGateway = $this->createStub(QnaQuestionGateway::class);
        $questionGateway->method('find')->willReturn(new QnaQuestion(23, 12, 7, 'Question', 100));

        return [$sessionGateway, $questionGateway, $this->createMock(QnaVoteGateway::class)];
    }

    private function service(
        QnaSessionGateway $sessionGateway,
        QnaQuestionGateway $questionGateway,
        QnaVoteGateway $voteGateway,
        int $memberId = 42,
    ): VoteService {
        $member = $this->createStub(FrontendUser::class);
        $member->method('__get')->willReturn($memberId);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($member);

        return new VoteService(
            $sessionGateway,
            $questionGateway,
            $voteGateway,
            new FrontendMemberProvider($security),
            $this->clock(),
        );
    }

    private function session(SessionState $state = SessionState::OPEN, bool $published = true): QnaSession
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
