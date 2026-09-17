<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use PHPUnit\Framework\TestCase;

final class QnaSessionGatewayTest extends TestCase
{
    public function testReopeningUsesClosedGuardAndIncrementsRound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->with(
            self::callback(static fn (string $sql): bool => str_contains($sql, 'round = round + 1')
                && str_contains($sql, 'endedAt = NULL')
                && str_contains($sql, 'state = :expectedState')),
            ['newState' => 'open', 'timestamp' => 123, 'id' => 7, 'expectedState' => 'closed'],
            self::anything(),
        )->willReturn(1);

        self::assertTrue((new QnaSessionGateway($connection))->markReopened(7, 123));
    }

    public function testAllSessionReadsHydrateRound(): void
    {
        $row = ['id' => 7, 'title' => 'Session', 'alias' => 'session', 'published' => '1',
            'state' => 'closed', 'startedAt' => 100, 'endedAt' => 120, 'round' => 3];
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(3))->method('fetchAssociative')
            ->with(self::stringContains('endedAt, round'), self::anything(), self::anything())->willReturn($row);
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->with(self::stringContains('endedAt, round'), self::anything(), self::anything())->willReturn([$row]);
        $gateway = new QnaSessionGateway($connection);
        self::assertSame(3, $gateway->find(7)?->round);
        self::assertSame(3, $gateway->findPublished(7)?->round);
        self::assertSame(3, $gateway->findPublishedByAlias('session')?->round);
        self::assertSame(3, $gateway->findAllPublished()[0]->round);
    }

    public function testPublishedListFiltersInTheDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'WHERE published = :published')),
                ['published' => '1'],
                self::anything(),
            )
            ->willReturn([]);

        self::assertSame([], (new QnaSessionGateway($connection))->findAllPublished());
    }
}
