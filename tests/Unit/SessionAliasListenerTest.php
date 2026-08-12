<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Slug\Slug;
use Contao\DC_Table;
use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\EventListener\DataContainer\Session\FieldsAliasSaveListener;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SessionAliasListenerTest extends TestCase
{
    public function testEmptyAliasIsGeneratedFromTitle(): void
    {
        $dataContainer = $this->createStub(DC_Table::class);
        $dataContainer->method('getActiveRecord')->willReturn(['id' => 12, 'title' => 'My Session']);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                self::stringContains('FROM tl_qna_session'),
                ['alias' => 'my-session', 'id' => 12],
                self::anything(),
            )
            ->willReturn(false);

        $slug = $this->createMock(Slug::class);
        $slug->expects(self::once())
            ->method('generate')
            ->willReturnCallback(
                static function (string $title, iterable $options, callable $duplicateCheck): string {
                    self::assertSame('My Session', $title);
                    self::assertSame([], $options);
                    self::assertFalse($duplicateCheck('my-session'));

                    return 'my-session';
                },
            );

        $listener = new FieldsAliasSaveListener(
            $slug,
            $connection,
            $this->createStub(TranslatorInterface::class),
        );

        self::assertSame('my-session', $listener('', $dataContainer));
    }

    public function testExistingManualAliasIsRejected(): void
    {
        $dataContainer = $this->createStub(DC_Table::class);
        $dataContainer->method('getActiveRecord')->willReturn(['id' => 12, 'title' => 'My Session']);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->willReturn(1);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())
            ->method('trans')
            ->with('ERR.aliasExists', ['existing'], 'contao_default')
            ->willReturn('Alias already exists.');

        $listener = new FieldsAliasSaveListener(
            $this->createStub(Slug::class),
            $connection,
            $translator,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Alias already exists.');

        $listener('existing', $dataContainer);
    }
}
