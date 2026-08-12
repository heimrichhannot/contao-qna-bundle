<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\View\QnaSessionListViewFactory;
use PHPUnit\Framework\TestCase;

final class QnaSessionListViewFactoryTest extends TestCase
{
    public function testReaderLinksUseContaosContentUrlGenerator(): void
    {
        $page = $this->createStub(PageModel::class);
        $urlGenerator = $this->createMock(ContentUrlGenerator::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with($page, ['parameters' => '/mobility'])
            ->willReturn('/question-sessions/mobility');

        $items = (new QnaSessionListViewFactory($urlGenerator))->create([
            new QnaSession(7, 'Mobility', 'mobility', true, SessionState::WAITING, null, null),
        ], $page);

        self::assertCount(1, $items);
        self::assertSame('Mobility', $items[0]->title);
        self::assertSame('/question-sessions/mobility', $items[0]->url);
    }
}
