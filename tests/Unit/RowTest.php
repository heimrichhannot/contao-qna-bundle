<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use HeimrichHannot\QnaBundle\Gateway\Row;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RowTest extends TestCase
{
    public function testValuesAreHydratedWithTheExistingConversions(): void
    {
        $row = new Row([
            'id' => '7',
            'startedAt' => null,
            'title' => 'Questions',
            'published' => '1',
        ]);

        self::assertSame(7, $row->int('id'));
        self::assertNull($row->nullableInt('startedAt'));
        self::assertSame('Questions', $row->string('title'));
        self::assertTrue($row->bool('published'));
    }

    /**
     * @return iterable<string, array{callable(Row): mixed, string}>
     */
    public static function invalidValueProvider(): iterable
    {
        yield 'integer' => [static fn (Row $row): int => $row->int('value'), 'Column "value" is not an integer value.'];
        yield 'string' => [static fn (Row $row): string => $row->string('value'), 'Column "value" is not a string value.'];
        yield 'boolean' => [static fn (Row $row): bool => $row->bool('value'), 'Column "value" is not a boolean value.'];
    }

    /** @param callable(Row): mixed $read */
    #[DataProvider('invalidValueProvider')]
    public function testInvalidValuesKeepTheirExistingMessages(callable $read, string $message): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $read(new Row(['value' => []]));
    }
}
