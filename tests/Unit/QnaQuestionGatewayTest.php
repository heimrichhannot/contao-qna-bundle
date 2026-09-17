<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Enum\QuestionSort;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QnaQuestionGatewayTest extends TestCase
{
    public function testCreatePersistsRound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('insert')->with('tl_qna_question',
            ['pid' => 7, 'round' => 3, 'memberId' => 42, 'question' => 'Question', 'createdAt' => 100, 'tstamp' => 100],
            self::anything(),
        );
        $connection->method('lastInsertId')->willReturn('23');
        self::assertSame(23, (new QnaQuestionGateway($connection))->create(7, 42, 'Question', 100, 3));
    }

    /**
     * @return iterable<string, array{QuestionSort, string}>
     */
    public static function sortingProvider(): iterable
    {
        yield 'votes' => [QuestionSort::VOTES, 'ORDER BY voteCount DESC, q.createdAt ASC'];
        yield 'time' => [QuestionSort::TIME, 'ORDER BY q.createdAt ASC'];
    }

    #[DataProvider('sortingProvider')]
    public function testQuestionListUsesOneQueryWithSpecifiedSorting(
        QuestionSort $sort,
        string $expectedOrder,
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'LEFT JOIN tl_qna_vote')
                    && str_contains($sql, 'q.voteCount')
                    && str_contains($sql, 'q.round = :round')
                    && !str_contains($sql, 'GROUP BY')
                    && str_contains($sql, 'v.memberId = :memberId')
                    && str_contains($sql, $expectedOrder)),
                ['sessionId' => 12, 'round' => 1, 'memberId' => 42],
                self::anything(),
            )
            ->willReturn([
                [
                    'id' => 23,
                    'pid' => 12,
                    'round' => 1,
                    'memberId' => 7,
                    'question' => 'Question',
                    'createdAt' => 100,
                    'answered' => 1,
                    'voteCount' => 3,
                    'hasVoted' => 1,
                    'isOwn' => 1,
                ],
            ]);

        $items = (new QnaQuestionGateway($connection))->findForSession(12, 1, 42, $sort);

        self::assertCount(1, $items);
        self::assertSame(3, $items[0]->voteCount);
        self::assertTrue($items[0]->hasVoted);
        self::assertTrue($items[0]->isOwn);
    }

    #[DataProvider('sortingProvider')]
    public function testStageSortingUsesCachedCountsWithoutMemberSpecificState(
        QuestionSort $sort,
        string $expectedOrder,
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'LEFT JOIN tl_qna_vote')
                    && str_contains($sql, 'q.voteCount')
                    && str_contains($sql, 'q.round = :round')
                    && !str_contains($sql, 'GROUP BY')
                    && str_contains($sql, 'v.memberId = :memberId')
                    && str_contains($sql, $expectedOrder)),
                ['sessionId' => 12, 'round' => 1, 'memberId' => 0],
                self::anything(),
            )
            ->willReturn([
                [
                    'id' => 23,
                    'pid' => 12,
                    'round' => 1,
                    'memberId' => 7,
                    'question' => 'Question',
                    'createdAt' => 100,
                    'answered' => 1,
                    'voteCount' => 3,
                    'hasVoted' => 0,
                    'isOwn' => 0,
                ],
            ]);

        $items = (new QnaQuestionGateway($connection))->findForStage(12, 1, $sort);

        self::assertCount(1, $items);
        self::assertSame(3, $items[0]->voteCount);
        self::assertFalse($items[0]->hasVoted);
        // The stage is member-agnostic: nobody's questions are marked as their own.
        self::assertFalse($items[0]->isOwn);
    }
}
