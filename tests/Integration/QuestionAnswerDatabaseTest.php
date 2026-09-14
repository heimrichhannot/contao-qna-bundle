<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Integration;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use HeimrichHannot\QnaBundle\Configuration\QnaOptions;
use HeimrichHannot\QnaBundle\Enum\QuestionSort;
use HeimrichHannot\QnaBundle\Exception\QuestionAnsweredException;
use HeimrichHannot\QnaBundle\Exception\QuestionCooldownException;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\LockedContextLoader;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;
use HeimrichHannot\QnaBundle\Migration\RebuildVoteCountMigration;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\MemberDataEraser;
use HeimrichHannot\QnaBundle\Service\QuestionAnswerService;
use HeimrichHannot\QnaBundle\Service\QuestionService;
use HeimrichHannot\QnaBundle\Service\SessionService;
use HeimrichHannot\QnaBundle\Service\VoteService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;

/** Opt-in tests against a migrated MySQL/MariaDB InnoDB database; fixtures are removed in tearDown. */
final class QuestionAnswerDatabaseTest extends TestCase
{
    private Connection $connection;
    private int $sessionId;
    private int $questionId;

    protected function setUp(): void
    {
        if ('1' !== getenv('QNA_DATABASE_TESTS')) {
            self::markTestSkipped('Set QNA_DATABASE_TESTS=1 to use a disposable migrated database.');
        }

        if (!\function_exists('pcntl_fork') || !\function_exists('posix_kill')) {
            self::fail('Concurrency tests require pcntl and posix.');
        }
        $this->connection = $this->connect();
        foreach (['tl_qna_session', 'tl_qna_question', 'tl_qna_vote'] as $table) {
            self::assertSame('InnoDB', $this->connection->fetchOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]));
        }
        $this->connection->insert('tl_qna_session', ['title' => 'Answered integration test', 'alias' => uniqid('qna-answer-test-', true), 'published' => '1', 'state' => 'open']);
        $this->sessionId = (int) $this->connection->lastInsertId();
        $this->questionId = (new QnaQuestionGateway($this->connection))->create($this->sessionId, 0, 'Temporary integration question', 100);
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection, $this->sessionId)) {
            return;
        }

        $this->connection->executeStatement('DELETE FROM tl_qna_vote WHERE pid IN (SELECT id FROM tl_qna_question WHERE pid = ?)', [$this->sessionId]);
        $this->connection->delete('tl_qna_question', ['pid' => $this->sessionId]);
        $this->connection->delete('tl_qna_session', ['id' => $this->sessionId]);
        $this->connection->close();
    }

    public function testGuestDoesNotOwnMemberZeroVote(): void
    {
        (new QnaVoteGateway($this->connection))->create($this->questionId, 0, 100);
        $questions = new QnaQuestionGateway($this->connection);
        self::assertFalse($questions->findForSession($this->sessionId, 0)[0]->hasVoted);
        self::assertFalse($questions->findForStage($this->sessionId)[0]->hasVoted);
        self::assertSame(1, $questions->findForSession($this->sessionId, 0)[0]->voteCount);
    }

    public function testNewQuestionIncludesAuthorVoteAndCannotBeVotedTwice(): void
    {
        $question = $this->questionService(new QnaVoteGateway($this->connection))->create($this->sessionId, 'Automatically voted question');

        try {
            $votes = new QnaVoteGateway($this->connection);
            self::assertSame(1, $votes->getState($question->id, 2147483647)->voteCount);
            self::assertTrue($votes->getState($question->id, 2147483647)->hasVoted);
            self::assertFalse($votes->getState($question->id, 2147483646)->hasVoted);
            self::assertSame(1, $this->voteService()->vote($this->sessionId, $question->id)->voteCount);
            self::assertSame(2, $this->voteService(2147483646)->vote($this->sessionId, $question->id)->voteCount);
        } finally {
            $this->connection->delete('tl_qna_vote', ['pid' => $question->id]);
        }
    }

    public function testFailedAuthorVoteRollsBackQuestion(): void
    {
        $votesBefore = $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM tl_qna_vote WHERE memberId = ?', [2147483647]));
        // Fail in the real database after inserting an author vote, exercising rollback of both rows.
        $votes = new class($this->connection) extends QnaVoteGateway {
            public function create(int $questionId, int $memberId, int $createdAt): void
            {
                parent::create($questionId, $memberId, $createdAt);
                parent::create($questionId, $memberId, $createdAt);
            }
        };

        try {
            $this->questionService($votes)->create($this->sessionId, 'Must be rolled back');
            self::fail('Expected vote failure.');
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            self::assertSame($votesBefore, $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM tl_qna_vote WHERE memberId = ?', [2147483647])));
        }

        self::assertFalse($this->connection->isTransactionActive());
        self::assertCount(1, (new QnaQuestionGateway($this->connection))->findForStage($this->sessionId));
    }

    public function testCountersSurviveVotesDuplicatesMemberAndQuestionDeletion(): void
    {
        $questions = new QnaQuestionGateway($this->connection);
        $votes = new QnaVoteGateway($this->connection);
        $this->voteService()->vote($this->sessionId, $this->questionId);
        $this->assertCounters();
        $this->voteService()->vote($this->sessionId, $this->questionId);
        $this->assertCounters();
        $this->voteService(2147483646)->vote($this->sessionId, $this->questionId);
        $this->assertCounters();
        $authored = $this->questionService($votes)->create($this->sessionId, 'Will be erased');
        $this->voteService(2147483646)->vote($this->sessionId, $authored->id);
        $this->assertCounters();
        (new MemberDataEraser($this->connection, $questions, $votes))->erase(2147483647);
        $this->assertCounters();
        self::assertNull($questions->find($authored->id));
        self::assertSame(1, $questions->findForStage($this->sessionId)[0]->voteCount);
        self::assertSame(0, $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM tl_qna_vote WHERE pid = ?', [$authored->id])));
        (new MemberDataEraser($this->connection, $questions, $votes))->erase(2147483646);
        $this->assertCounters();
        self::assertSame(0, $questions->findForStage($this->sessionId)[0]->voteCount);
    }

    #[DataProvider('lockOrders')]
    public function testErasureCoordinatesWithVoting(bool $eraseFirst): void
    {
        $this->voteService()->vote($this->sessionId, $this->questionId);
        $erase = fn () => (new MemberDataEraser($this->connection, new QnaQuestionGateway($this->connection), new QnaVoteGateway($this->connection)))->erase(2147483647);
        $vote = fn () => $this->operate('vote');
        $this->overlap($eraseFirst ? $erase : $vote, $eraseFirst ? $vote : $erase, 'ok', true);
        $this->assertCounters();
        self::assertSame($eraseFirst ? 1 : 0, (new QnaQuestionGateway($this->connection))->findForStage($this->sessionId)[0]->voteCount);
    }

    public function testReaderUsesCachedCountsAndMemberVoteLookupWithBothSorts(): void
    {
        $questions = new QnaQuestionGateway($this->connection);
        $second = $questions->create($this->sessionId, 0, 'Later popular question', 200);
        $this->voteService()->vote($this->sessionId, $second);
        $this->voteService(2147483646)->vote($this->sessionId, $second);
        $this->assertCounters();
        $byVotes = $questions->findForSession($this->sessionId, 2147483647, QuestionSort::VOTES);
        self::assertSame([$second, $this->questionId], array_column($byVotes, 'id'));
        self::assertSame([2, 0], array_column($byVotes, 'voteCount'));
        self::assertSame([true, false], array_column($byVotes, 'hasVoted'));
        self::assertSame([false, false], array_column($questions->findForSession($this->sessionId, 123), 'hasVoted'));
        self::assertSame([$this->questionId, $second], array_column($questions->findForStage($this->sessionId, QuestionSort::TIME), 'id'));
        self::assertSame([false, false], array_column($questions->findForStage($this->sessionId), 'hasVoted'));
    }

    public function testMigrationRebuildsCorruptCountersAndIsIdempotent(): void
    {
        $this->voteService()->vote($this->sessionId, $this->questionId);
        $this->connection->update('tl_qna_question', ['voteCount' => 99], ['id' => $this->questionId]);
        $migration = new RebuildVoteCountMigration($this->connection);
        self::assertTrue($migration->shouldRun());
        self::assertTrue($migration->run()->isSuccessful());
        $this->assertCounters();
        self::assertFalse($migration->shouldRun());
        self::assertTrue($migration->run()->isSuccessful());
        $this->assertCounters();
    }

    private function assertCounters(): void
    {
        self::assertSame([], $this->connection->fetchFirstColumn(
            'SELECT q.id FROM tl_qna_question q WHERE q.pid = ? AND q.voteCount <> (SELECT COUNT(*) FROM tl_qna_vote v WHERE v.pid = q.id)',
            [$this->sessionId],
        ));
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
            new QnaOptions(2500, 500, 20, 4, 16),
            $votes,
            $this->connection,
        );
    }

    public function testMarkUndoPreservesVotesAndRestoresVoting(): void
    {
        $questions = new QnaQuestionGateway($this->connection);
        self::assertFalse($questions->find($this->questionId)?->answered);
        $this->voteService()->vote($this->sessionId, $this->questionId);
        $this->answerService()->setAnswered($this->sessionId, $this->questionId, true);
        $this->answerService()->setAnswered($this->sessionId, $this->questionId, true);
        try {
            $this->voteService()->vote($this->sessionId, $this->questionId);
            self::fail('Answered vote must be rejected.');
        } catch (QuestionAnsweredException) {
            self::assertFalse($this->connection->isTransactionActive());
        }
        self::assertSame(1, $questions->findForStage($this->sessionId)[0]->voteCount);
        self::assertTrue($questions->findForSession($this->sessionId, 0)[0]->answered);
        $this->answerService()->setAnswered($this->sessionId, $this->questionId, false);
        $this->answerService()->setAnswered($this->sessionId, $this->questionId, false);
        self::assertTrue($this->voteService()->vote($this->sessionId, $this->questionId)->hasVoted);
        self::assertTrue($this->voteService(2147483646)->vote($this->sessionId, $this->questionId)->hasVoted);
        self::assertSame(2, $questions->findForStage($this->sessionId)[0]->voteCount);
    }

    /** @return iterable<string, array{bool}> */
    public static function lockOrders(): iterable
    {
        yield 'first ordering' => [true];
        yield 'reverse ordering' => [false];
    }

    /** @return iterable<string, array{string, bool}> */
    public static function closureOrders(): iterable
    {
        foreach (['submit', 'vote', 'answer', 'unanswer'] as $operation) {
            yield $operation.' before close' => [$operation, false];
            yield 'close before '.$operation => [$operation, true];
        }
    }

    #[DataProvider('closureOrders')]
    public function testClosureCoordinatesWithEveryWrite(string $operation, bool $closeFirst): void
    {
        if ('unanswer' === $operation) {
            $this->answerService()->setAnswered($this->sessionId, $this->questionId, true);
        }
        $write = fn () => $this->operate($operation);
        $close = fn () => $this->sessionService()->stop($this->sessionId);
        $this->overlap($closeFirst ? $close : $write, $closeFirst ? $write : $close, $closeFirst ? SessionNotOpenException::class : 'ok');
        self::assertSame('closed', $this->connection->fetchOne('SELECT state FROM tl_qna_session WHERE id = ?', [$this->sessionId]));
        self::assertSame('submit' === $operation && !$closeFirst ? 2 : 1, $this->questionCount());
        self::assertSame(\in_array($operation, ['submit', 'vote'], true) && !$closeFirst ? 1 : 0, $this->voteCount());
        self::assertSame('answer' === $operation ? !$closeFirst : ('unanswer' === $operation && $closeFirst), (new QnaQuestionGateway($this->connection))->find($this->questionId)?->answered);
    }

    /** @return iterable<string, array{bool}> */
    public static function cooldownHistories(): iterable
    {
        yield 'empty session' => [false];
        yield 'expired previous question' => [true];
    }

    #[DataProvider('cooldownHistories')]
    public function testConcurrentSubmissionsEnforceCooldownEvenWithoutPreviousQuestion(bool $withPrevious): void
    {
        $this->connection->delete('tl_qna_question', ['id' => $this->questionId]);
        if ($withPrevious) {
            (new QnaQuestionGateway($this->connection))->create($this->sessionId, 2147483647, 'Older question outside cooldown', 100);
        }
        // The child starts a repeatable-read snapshot before the first submission.
        $this->overlap(fn () => $this->operate('submit'), fn () => $this->operate('submit'), QuestionCooldownException::class, true);
        self::assertSame($withPrevious ? 2 : 1, $this->questionCount());
        self::assertSame(1, $this->voteCount());
        $this->assertCounters();
        self::assertSame(1, (new QnaQuestionGateway($this->connection))->findForStage($this->sessionId)[0]->voteCount);
    }

    #[DataProvider('lockOrders')]
    public function testAnsweringAndVotingSerialize(bool $answerFirst): void
    {
        $answer = fn () => $this->operate('answer');
        $vote = fn () => $this->operate('vote');
        $this->overlap($answerFirst ? $answer : $vote, $answerFirst ? $vote : $answer, $answerFirst ? QuestionAnsweredException::class : 'ok');
        self::assertTrue((new QnaQuestionGateway($this->connection))->find($this->questionId)?->answered);
        self::assertSame($answerFirst ? 0 : 1, $this->voteCount());
    }

    public function testConcurrentDuplicateVotingIsIdempotentWithAnOldSnapshot(): void
    {
        $this->overlap(fn () => $this->operate('vote'), function (): void {
            $state = $this->voteService()->vote($this->sessionId, $this->questionId);
            self::assertTrue($state->hasVoted);
            self::assertSame(1, $state->voteCount);
        }, 'ok', true);
        self::assertSame(1, $this->voteCount());
        $this->assertCounters();
        self::assertSame(1, (new QnaQuestionGateway($this->connection))->findForStage($this->sessionId)[0]->voteCount);
    }

    #[DataProvider('lockOrders')]
    public function testBackendUnpublicationCoordinatesWithSubmission(bool $unpublishFirst): void
    {
        $unpublish = fn () => $this->connection->update('tl_qna_session', ['published' => ''], ['id' => $this->sessionId]);
        $submit = fn () => $this->operate('submit');
        $this->overlap($unpublishFirst ? $unpublish : $submit, $unpublishFirst ? $submit : $unpublish, $unpublishFirst ? SessionNotPublishedException::class : 'ok');
        self::assertSame($unpublishFirst ? 1 : 2, $this->questionCount());
        self::assertSame($unpublishFirst ? 0 : 1, $this->voteCount());
    }

    public function testStartCoordinatesWithSubmission(): void
    {
        $this->connection->update('tl_qna_session', ['state' => 'waiting'], ['id' => $this->sessionId]);
        $this->overlap(fn () => $this->sessionService()->start($this->sessionId), fn () => $this->operate('submit'), 'ok');
        self::assertSame(2, $this->questionCount());
        self::assertSame(1, $this->voteCount());
        self::assertSame('open', $this->connection->fetchOne('SELECT state FROM tl_qna_session WHERE id = ?', [$this->sessionId]));
    }

    private function operate(string $operation): void
    {
        match ($operation) {
            'submit' => $this->questionService(new QnaVoteGateway($this->connection))->create($this->sessionId, 'Concurrent question'),
            'vote' => $this->voteService()->vote($this->sessionId, $this->questionId),
            'answer' => $this->answerService()->setAnswered($this->sessionId, $this->questionId, true),
            'unanswer' => $this->answerService()->setAnswered($this->sessionId, $this->questionId, false),
            default => throw new \LogicException('Unknown test operation'),
        };
    }

    /**
     * Hold the first service's transaction open until InnoDB proves the second is waiting.
     * Both processes own separate sockets. The observer needs PROCESS privilege.
     */
    private function overlap(callable $first, callable $second, string $expected, bool $oldSnapshot = false): void
    {
        $this->connection->close();
        $directory = sys_get_temp_dir().'/qna-lock-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid);
        if (0 === $pid) {
            try {
                $this->connection = $this->connect();
                if ($oldSnapshot) {
                    $this->connection->beginTransaction();
                    $this->questionCount();
                    $this->voteCount();
                }
                file_put_contents($directory.'/ready', (string) $this->integer($this->connection->fetchOne('SELECT CONNECTION_ID()')));
                $this->await(static fn () => is_file($directory.'/go'));
                $second();
                if ($this->connection->isTransactionActive()) {
                    $this->connection->commit();
                }
                file_put_contents($directory.'/result', 'ok');
            } catch (\Throwable $exception) {
                file_put_contents($directory.'/result', $exception::class);
                file_put_contents($directory.'/error', $exception->getMessage());
            } finally {
                $this->connection->close();
            }
            exit(0);
        }

        $observer = null;
        try {
            $this->await(static fn () => is_file($directory.'/ready'));
            $childConnectionId = (int) file_get_contents($directory.'/ready');
            $this->connection = $this->connect();
            $this->connection->beginTransaction();
            $first();
            file_put_contents($directory.'/go', 'go');
            $observer = $this->connect(true);
            $this->await(static function () use ($observer, $childConnectionId, $directory): bool {
                if (is_file($directory.'/result')) {
                    self::fail('Second operation finished without waiting: '.file_get_contents($directory.'/result'));
                }

                $query = $observer->fetchOne("SELECT trx_query FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id = ? AND trx_state = 'LOCK WAIT'", [$childConnectionId]);
                if (false === $query) {
                    return false;
                }
                self::assertIsString($query);
                self::assertStringContainsString('tl_qna_session', $query, 'The first contested lock must be the session, not a question or vote.');

                return true;
            });
            $this->connection->commit();
            $this->await(static fn () => is_file($directory.'/result'));
            self::assertSame($expected, file_get_contents($directory.'/result'), is_file($directory.'/error') ? (string) file_get_contents($directory.'/error') : '');
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            // Bound cleanup even when an assertion or database connection fails.
            posix_kill($pid, \SIGTERM);
            pcntl_waitpid($pid, $status);
            $observer?->close();
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    private function await(callable $condition): void
    {
        $deadline = microtime(true) + 10;
        while (!$condition()) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('Timed out waiting for concurrency barrier.');
            }
            // InnoDB instrumentation caches results; allow its 100 ms refresh interval.
            usleep(200000);
        }
    }

    private function connect(bool $observer = false): Connection
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => getenv('QNA_TEST_DB_HOST') ?: 'db',
            'port' => (int) (getenv('QNA_TEST_DB_PORT') ?: 3306),
            'dbname' => getenv('QNA_TEST_DB_NAME') ?: 'db',
            'user' => ($observer ? getenv('QNA_TEST_OBSERVER_USER') : false) ?: (getenv('QNA_TEST_DB_USER') ?: 'db'),
            'password' => ($observer ? getenv('QNA_TEST_OBSERVER_PASSWORD') : false) ?: (getenv('QNA_TEST_DB_PASSWORD') ?: 'db'),
        ]);
        $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 10');
        $connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        // DBAL 3 needs explicit savepoints for the outer test transaction; DBAL 4 always uses them.
        if (method_exists($connection, 'setNestTransactionsWithSavepoints')) { // @phpstan-ignore function.alreadyNarrowedType
            $connection->setNestTransactionsWithSavepoints(true);
        }

        return $connection;
    }

    private function integer(mixed $value): int
    {
        self::assertTrue(\is_int($value) || \is_string($value));

        return (int) $value;
    }

    private function questionCount(): int
    {
        return $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM tl_qna_question WHERE pid = ?', [$this->sessionId]));
    }

    private function voteCount(): int
    {
        return $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM tl_qna_vote WHERE pid IN (SELECT id FROM tl_qna_question WHERE pid = ?)', [$this->sessionId]));
    }

    private function sessionService(): SessionService
    {
        return new SessionService(new QnaSessionGateway($this->connection), new MockClock('@150'), $this->connection);
    }

    private function answerService(): QuestionAnswerService
    {
        $sessions = new QnaSessionGateway($this->connection);
        $questions = new QnaQuestionGateway($this->connection);

        return new QuestionAnswerService(new LockedContextLoader($sessions, $questions), $questions, $this->connection);
    }

    private function voteService(int $memberId = 2147483647): VoteService
    {
        $member = $this->createStub(FrontendUser::class);
        $member->method('__get')->willReturn($memberId);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($member);

        return new VoteService(new LockedContextLoader(new QnaSessionGateway($this->connection), new QnaQuestionGateway($this->connection)), new QnaVoteGateway($this->connection), new FrontendMemberProvider($security), new MockClock('@100'), $this->connection);
    }
}
