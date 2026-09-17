<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use HeimrichHannot\ContaoUxTurboEncore\EncoreExtension;
use HeimrichHannot\QnaBundle\Asset\TurboEntryAvailability;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TurboEntryAvailabilityTest extends TestCase
{
    #[DataProvider('pageCases')]
    public function testPageEntries(string $origin, mixed $blob, bool $enabled, bool $expected): void
    {
        $page = $this->createStub(PageModel::class);
        $page->method('__get')->willReturnMap([['id', 3], ['layout', 5], ['trail', [0, 1, 3]]]);
        $page->method('row')->willReturn(['encoreEntries' => 'page' === $origin ? $blob : null]);
        $parent = $this->createStub(PageModel::class);
        $parent->method('row')->willReturn(['encoreEntries' => 'parent' === $origin ? $blob : null]);
        $layout = $this->createStub(LayoutModel::class);
        $layout->method('row')->willReturn(['addEncore' => $enabled ? '1' : '', 'encoreEntries' => 'layout' === $origin ? $blob : null]);
        $layoutAdapter = $this->createMock(Adapter::class);
        $layoutAdapter->expects(self::once())->method('__call')->with('findById', [5])->willReturn($layout);
        $pageAdapter = $this->createStub(Adapter::class);
        $pageAdapter->method('__call')->willReturnCallback(static function (string $method, array $arguments) use ($parent): PageModel {
            self::assertSame('findById', $method);
            self::assertSame([1], $arguments);

            return $parent;
        });
        $framework = $this->createStub(ContaoFramework::class);
        $framework->method('getAdapter')->willReturnMap([
            [LayoutModel::class, $layoutAdapter], [PageModel::class, $pageAdapter], [StringUtil::class, new Adapter(StringUtil::class)],
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('createSchemaManager');

        self::assertSame($expected, (new TurboEntryAvailability($framework, $connection))->isActiveForPage($page));
    }

    /** @return iterable<string, array{string, mixed, bool, bool}> */
    public static function pageCases(): iterable
    {
        foreach (['layout', 'parent', 'page'] as $origin) {
            yield $origin => [$origin, serialize([['entry' => EncoreExtension::DEFAULT, 'active' => '1']]), true, true];
            yield $origin.' disabled layout' => [$origin, serialize([['entry' => EncoreExtension::DEFAULT, 'active' => '1']]), false, false];
        }
        yield 'no drive' => ['layout', serialize([['entry' => EncoreExtension::NO_DRIVER, 'active' => '1']]), true, true];
        yield 'inactive' => ['layout', serialize([['entry' => EncoreExtension::DEFAULT, 'active' => '']]), true, false];
        yield 'zero' => ['layout', serialize([['entry' => EncoreExtension::DEFAULT, 'active' => '0']]), true, false];
        yield 'missing active' => ['layout', serialize([['entry' => EncoreExtension::DEFAULT]]), true, true];
        yield 'null active matches isset semantics' => ['layout', serialize([['entry' => EncoreExtension::DEFAULT, 'active' => null]]), true, true];
        yield 'other entry' => ['layout', serialize([['entry' => 'huh_qna', 'active' => '1']]), true, false];
        yield 'null' => ['layout', null, true, false];
        yield 'empty' => ['layout', '', true, false];
        yield 'malformed' => ['layout', serialize(['invalid', ['active' => '1']]), true, false];
    }

    public function testMissingLayoutOrEncoreFields(): void
    {
        $page = $this->createStub(PageModel::class);
        $page->method('__get')->willReturn(5);
        $layoutAdapter = $this->createStub(Adapter::class);
        $layoutWithoutEncore = $this->createStub(LayoutModel::class);
        $layoutWithoutEncore->method('row')->willReturn([]);
        $layoutAdapter->method('__call')->willReturnOnConsecutiveCalls(null, $layoutWithoutEncore);
        $framework = $this->createStub(ContaoFramework::class);
        $framework->method('getAdapter')->willReturn($layoutAdapter);
        $service = new TurboEntryAvailability($framework, $this->createStub(Connection::class));

        self::assertFalse($service->isActiveForPage($page));
        self::assertFalse($service->isActiveForPage($page));
    }

    #[DataProvider('globalCases')]
    public function testGlobalEntries(?string $missing, bool $layoutHit, bool $pageHit, bool $expected): void
    {
        $column = new Column('encoreEntries', Type::getType(Types::BLOB));
        $schema = $this->createMock(MySQLSchemaManager::class);
        $schema->expects(self::exactly(2))->method('listTableColumns')->willReturnCallback(static function (string $table) use ($missing, $column): array {
            $columns = ['encoreentries' => $column];
            if ('tl_layout' === $table) {
                $columns['addencore'] = $column;
            }
            if ($missing === $table) {
                unset($columns['encoreentries']);
            } elseif ('addEncore' === $missing && 'tl_layout' === $table) {
                unset($columns['addencore']);
            }

            return $columns;
        });
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('createSchemaManager')->willReturn($schema);
        $connection->expects(null !== $missing ? self::never() : self::exactly($layoutHit ? 1 : 2))->method('iterateColumn')->willReturnCallback(static function (string $sql) use ($layoutHit, $pageHit): iterable {
            self::assertContains($sql, ["SELECT encoreEntries FROM tl_layout WHERE addEncore = '1'", 'SELECT encoreEntries FROM tl_page WHERE encoreEntries IS NOT NULL']);
            $hit = str_contains($sql, 'tl_layout') ? $layoutHit : $pageHit;
            yield null;
            yield serialize([['entry' => $hit ? EncoreExtension::NO_DRIVER : 'huh_qna', 'active' => '1']]);
        });
        $framework = $this->createStub(ContaoFramework::class);
        $framework->method('getAdapter')->willReturn(new Adapter(StringUtil::class));
        self::assertSame($expected, (new TurboEntryAvailability($framework, $connection))->isActiveAnywhere());
    }

    /** @return iterable<string, array{?string, bool, bool, bool}> */
    public static function globalCases(): iterable
    {
        yield 'layout' => [null, true, false, true];
        yield 'page' => [null, false, true, true];
        yield 'none' => [null, false, false, false];
        yield 'layout column missing' => ['tl_layout', true, true, false];
        yield 'page column missing' => ['tl_page', true, true, false];
        yield 'enable column missing' => ['addEncore', true, true, false];
    }
}
