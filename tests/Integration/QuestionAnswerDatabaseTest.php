<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Integration;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use HeimrichHannot\QnaBundle\Exception\QuestionAnsweredException;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\QuestionAnswerService;
use HeimrichHannot\QnaBundle\Service\QuestionService;
use HeimrichHannot\QnaBundle\Service\VoteService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;

/** Run explicitly in contao0507.contao; all fixtures are removed in tearDown. */
final class QuestionAnswerDatabaseTest extends TestCase
{
    private Connection $connection;
    private int $sessionId;
    private int $questionId;

    protected function setUp(): void
    {
        if ('contao0507.contao' !== getenv('DDEV_PROJECT')) {
            self::markTestSkipped('Requires the contao0507.contao DDEV database.');
        }

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'host' => 'db', 'dbname' => 'db', 'user' => 'db', 'password' => 'db']);
        $this->connection->insert('tl_qna_session', ['title' => 'Answered integration test', 'alias' => uniqid('qna-answer-test-', true), 'published' => '1', 'state' => 'open']);
        $this->sessionId = (int) $this->connection->lastInsertId();
        $this->questionId = (new QnaQuestionGateway($this->connection))->create($this->sessionId, 0, 'Temporary integration question', 100);
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }

        $this->connection->delete('tl_qna_vote', ['pid' => $this->questionId]);
        $this->connection->delete('tl_qna_question', ['pid' => $this->sessionId]);
        $this->connection->delete('tl_qna_session', ['id' => $this->sessionId]);
        $this->connection->close();
    }

    public function testNewQuestionIncludesAuthorVoteAndCannotBeVotedTwice(): void
    {
        $question = $this->questionService(new QnaVoteGateway($this->connection))->create($this->sessionId, 'Automatically voted question');

        try {
            $votes = new QnaVoteGateway($this->connection);
            self::assertSame(1, $votes->getState($question->id, 2147483647)->voteCount);
            self::assertTrue($votes->getState($question->id, 2147483647)->hasVoted);
            self::assertFalse($votes->getState($question->id, 2147483646)->hasVoted);
            self::assertSame(1, $this->voteService()->vote($question->id, $this->sessionId)->voteCount);
            self::assertSame(2, $this->voteService(2147483646)->vote($question->id, $this->sessionId)->voteCount);
        } finally {
            $this->connection->delete('tl_qna_vote', ['pid' => $question->id]);
        }
    }

    public function testFailedAuthorVoteRollsBackQuestion(): void
    {
        $votes = $this->createMock(QnaVoteGateway::class);
        $votes->expects(self::once())->method('create')->willThrowException(new \RuntimeException('Vote insert failed'));

        try {
            $this->questionService($votes)->create($this->sessionId, 'Must be rolled back');
            self::fail('Expected vote failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Vote insert failed', $exception->getMessage());
        }

        self::assertFalse($this->connection->isTransactionActive());
        self::assertCount(1, (new QnaQuestionGateway($this->connection))->findForStage($this->sessionId));
    }

    private function questionService(QnaVoteGateway $votes): QuestionService
    {
        $member = $this->createStub(FrontendUser::class);
        $member->method('__get')->willReturn(2147483647);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($member);

        return new QuestionService(
            new QnaSessionGateway($this->connection),
            new QnaQuestionGateway($this->connection),
            new FrontendMemberProvider($security),
            new MockClock('@150'),
            500,
            20,
            $votes,
            $this->connection,
        );
    }

    public function testMarkUndoPreservesVotesAndRestoresVoting(): void
    {
        $questions = new QnaQuestionGateway($this->connection);
        self::assertFalse($questions->find($this->questionId)?->answered);
        $this->voteService()->vote($this->questionId, $this->sessionId);
        $this->answerService()->setAnswered($this->sessionId, $this->questionId, true);
        $this->answerService()->setAnswered($this->sessionId, $this->questionId, true);
        try {
            $this->voteService()->vote($this->questionId, $this->sessionId);
            self::fail('Answered vote must be rejected.');
        } catch (QuestionAnsweredException) {
            self::assertFalse($this->connection->isTransactionActive());
        }
        self::assertSame(1, $questions->findForStage($this->sessionId)[0]->voteCount);
        self::assertTrue($questions->findForSession($this->sessionId, 0)[0]->answered);
        $this->answerService()->setAnswered($this->sessionId, $this->questionId, false);
        $this->answerService()->setAnswered($this->sessionId, $this->questionId, false);
        self::assertTrue($this->voteService()->vote($this->questionId, $this->sessionId)->hasVoted);
        self::assertTrue($this->voteService(2147483646)->vote($this->questionId, $this->sessionId)->hasVoted);
        self::assertSame(2, $questions->findForStage($this->sessionId)[0]->voteCount);
    }

    /** @return iterable<string, array{bool}> */
    public static function lockOrders(): iterable
    {
        yield 'mark wins lock' => [true];
        yield 'vote wins lock' => [false];
    }

    #[DataProvider('lockOrders')]
    public function testConcurrentMarkAndVoteSerializeOnQuestion(bool $markFirst): void
    {
        // Fork with no open database socket so each process owns its connection.
        $this->connection->close();
        $signal = tempnam(sys_get_temp_dir(), 'qna-lock-');
        self::assertIsString($signal);
        $result = $signal.'.result';
        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid);
        if (0 === $pid) {
            try {
                $deadline = microtime(true) + 10;
                while ('locked' !== file_get_contents($signal)) {
                    if (microtime(true) > $deadline) {
                        throw new \RuntimeException('Parent did not acquire lock.');
                    }
                    usleep(10000);
                }
                file_put_contents($result, 'started');
                if ($markFirst) {
                    try {
                        $this->voteService()->vote($this->questionId, $this->sessionId);
                        file_put_contents($result, 'unexpected vote');
                    } catch (QuestionAnsweredException) {
                        file_put_contents($result, 'rejected');
                    }
                } else {
                    $this->answerService()->setAnswered($this->sessionId, $this->questionId, true);
                    file_put_contents($result, 'marked');
                }
            } catch (\Throwable $exception) {
                file_put_contents($result, $exception->getMessage());
            }
            exit(0);
        }

        try {
            $this->connection->beginTransaction();
            (new QnaQuestionGateway($this->connection))->find($this->questionId, true);
            if ($markFirst) {
                $this->answerService()->setAnswered($this->sessionId, $this->questionId, true);
            } else {
                $this->voteService()->vote($this->questionId, $this->sessionId);
            }
            file_put_contents($signal, 'locked');
            $deadline = microtime(true) + 10;
            while (!is_file($result) && microtime(true) < $deadline) {
                usleep(10000);
            }
            usleep(200000);
            self::assertSame('started', file_get_contents($result), 'The second operation must wait for the question lock.');
            $this->connection->commit();
            pcntl_waitpid($pid, $status);
            self::assertSame($markFirst ? 'rejected' : 'marked', file_get_contents($result));
            $question = (new QnaQuestionGateway($this->connection))->findForStage($this->sessionId)[0];
            self::assertTrue($question->answered);
            self::assertSame($markFirst ? 0 : 1, $question->voteCount);
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            pcntl_waitpid($pid, $status);
            unlink($signal);
            if (is_file($result)) {
                unlink($result);
            }
        }
    }

    private function answerService(): QuestionAnswerService
    {
        return new QuestionAnswerService(new QnaSessionGateway($this->connection), new QnaQuestionGateway($this->connection), $this->connection);
    }

    private function voteService(int $memberId = 2147483647): VoteService
    {
        $member = $this->createStub(FrontendUser::class);
        $member->method('__get')->willReturn($memberId);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($member);

        return new VoteService(new QnaSessionGateway($this->connection), new QnaQuestionGateway($this->connection), new QnaVoteGateway($this->connection), new FrontendMemberProvider($security), new MockClock('@100'), $this->connection);
    }
}
