<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\FrontendUser;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\AuthenticationRequiredException;
use HeimrichHannot\QnaBundle\Exception\EmptyQuestionException;
use HeimrichHannot\QnaBundle\Exception\QuestionCooldownException;
use HeimrichHannot\QnaBundle\Exception\QuestionTooLongException;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\QuestionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class QuestionServiceTest extends TestCase
{
    public function testCreatesTrimmedQuestionForMemberFromSecurityContext(): void
    {
        $sessionGateway = $this->createMock(QnaSessionGateway::class);
        $sessionGateway->expects(self::once())->method('find')->with(12)->willReturn($this->session(SessionState::OPEN));

        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::once())->method('findLatestCreatedAt')->with(12, 42)->willReturn(null);
        $questionGateway->expects(self::once())
            ->method('create')
            ->with(12, 42, 'How does this work?', 1_700_000_000)
            ->willReturn(99);

        $question = $this->service($sessionGateway, $questionGateway)->create(12, '  How does this work?  ');

        self::assertSame(99, $question->id);
        self::assertSame(42, $question->memberId);
        self::assertSame('How does this work?', $question->question);
    }

    /**
     * @return iterable<string, array{SessionState}>
     */
    public static function nonOpenStateProvider(): iterable
    {
        yield 'waiting' => [SessionState::WAITING];
        yield 'closed' => [SessionState::CLOSED];
    }

    #[DataProvider('nonOpenStateProvider')]
    public function testQuestionIsRejectedUnlessSessionIsOpen(SessionState $state): void
    {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session($state));
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::never())->method('create');

        $this->expectException(SessionNotOpenException::class);

        $this->service($sessionGateway, $questionGateway)->create(12, 'Question');
    }

    public function testQuestionIsRejectedForUnpublishedSession(): void
    {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session(SessionState::OPEN, false));
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::never())->method('create');

        $this->expectException(SessionNotPublishedException::class);

        $this->service($sessionGateway, $questionGateway)->create(12, 'Question');
    }

    public function testQuestionRequiresAuthenticatedFrontendMember(): void
    {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session(SessionState::OPEN));
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::never())->method('create');

        $this->expectException(AuthenticationRequiredException::class);

        $this->service($sessionGateway, $questionGateway, null)->create(12, 'Question');
    }

    public function testWhitespaceOnlyQuestionIsRejected(): void
    {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session(SessionState::OPEN));
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::never())->method('create');

        $this->expectException(EmptyQuestionException::class);

        $this->service($sessionGateway, $questionGateway)->create(12, " \n\t ");
    }

    public function testMaximumQuestionLengthIsEnforced(): void
    {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session(SessionState::OPEN));
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::never())->method('create');

        $this->expectException(QuestionTooLongException::class);

        $this->service($sessionGateway, $questionGateway, 42, 5)->create(12, '123456');
    }

    public function testCooldownIsEnforcedPerSessionAndMember(): void
    {
        $sessionGateway = $this->createStub(QnaSessionGateway::class);
        $sessionGateway->method('find')->willReturn($this->session(SessionState::OPEN));
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::once())->method('findLatestCreatedAt')->with(12, 42)->willReturn(1_699_999_990);
        $questionGateway->expects(self::never())->method('create');

        $this->expectException(QuestionCooldownException::class);

        $this->service($sessionGateway, $questionGateway)->create(12, 'Question');
    }

    private function service(
        QnaSessionGateway $sessionGateway,
        QnaQuestionGateway $questionGateway,
        ?int $memberId = 42,
        int $maxQuestionLength = 500,
    ): QuestionService {
        $security = $this->createStub(Security::class);

        if (null === $memberId) {
            $security->method('getUser')->willReturn(null);
        } else {
            $member = $this->createStub(FrontendUser::class);
            $member->method('__get')->willReturn($memberId);
            $security->method('getUser')->willReturn($member);
        }

        return new QuestionService(
            $sessionGateway,
            $questionGateway,
            new FrontendMemberProvider($security),
            $this->clock(),
            $maxQuestionLength,
            20,
        );
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
