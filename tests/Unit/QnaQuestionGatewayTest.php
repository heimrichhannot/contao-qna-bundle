<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QnaQuestionGatewayTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sortingProvider(): iterable
    {
        yield 'votes' => ['votes', 'ORDER BY voteCount DESC, q.createdAt ASC'];
        yield 'time' => ['time', 'ORDER BY q.createdAt ASC'];
        yield 'invalid values normalize to votes' => ['anything', 'ORDER BY voteCount DESC, q.createdAt ASC'];
    }

    #[DataProvider('sortingProvider')]
    public function testQuestionListUsesOneAggregatedQueryWithSpecifiedSorting(
        string $sort,
        string $expectedOrder,
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'LEFT JOIN tl_qna_vote')
                    && str_contains($sql, 'COUNT(v.id) AS voteCount')
                    && str_contains($sql, 'MAX(CASE WHEN v.memberId = :memberId')
                    && str_contains($sql, $expectedOrder)),
                ['sessionId' => 12, 'memberId' => 42],
                self::anything(),
            )
            ->willReturn([
                [
                    'id' => 23,
                    'pid' => 12,
                    'memberId' => 7,
                    'question' => 'Question',
                    'createdAt' => 100,
                    'voteCount' => 3,
                    'hasVoted' => 1,
                ],
            ]);

        $items = (new QnaQuestionGateway($connection))->findForSession(12, 42, $sort);

        self::assertCount(1, $items);
        self::assertSame(3, $items[0]->voteCount);
        self::assertTrue($items[0]->hasVoted);
    }

    #[DataProvider('sortingProvider')]
    public function testStageSortingUsesAggregationWithoutMemberSpecificState(
        string $sort,
        string $expectedOrder,
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'LEFT JOIN tl_qna_vote')
                    && str_contains($sql, 'COUNT(v.id) AS voteCount')
                    && str_contains($sql, '0 AS hasVoted')
                    && str_contains($sql, $expectedOrder)),
                ['sessionId' => 12],
                self::anything(),
            )
            ->willReturn([
                [
                    'id' => 23,
                    'pid' => 12,
                    'memberId' => 7,
                    'question' => 'Question',
                    'createdAt' => 100,
                    'voteCount' => 3,
                    'hasVoted' => 0,
                ],
            ]);

        $items = (new QnaQuestionGateway($connection))->findForStage(12, $sort);

        self::assertCount(1, $items);
        self::assertSame(3, $items[0]->voteCount);
        self::assertFalse($items[0]->hasVoted);
    }
}
