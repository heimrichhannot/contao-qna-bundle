<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use PHPUnit\Framework\TestCase;

final class QnaSessionGatewayTest extends TestCase
{
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
