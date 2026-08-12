<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\Input;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Controller\ContentElement\QnaSessionReaderController;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\View\QnaReaderViewFactory;
use HeimrichHannot\QnaBundle\View\QnaSessionListViewFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QnaSessionReaderControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null}>
     */
    public static function missingItemProvider(): iterable
    {
        yield 'absent item' => [null];
        yield 'empty item' => [''];
    }

    #[DataProvider('missingItemProvider')]
    public function testMissingItemThrowsPageNotFound(?string $alias): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::never())->method('findPublishedByAlias');
        [$controller] = $this->createController($alias, $gateway);

        $this->expectException(PageNotFoundException::class);
        $controller->resolveForTest();
    }

    public function testUnknownAliasThrowsPageNotFound(): void
    {
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())
            ->method('findPublishedByAlias')
            ->with('unknown')
            ->willReturn(null);
        [$controller] = $this->createController('unknown', $gateway);

        $this->expectException(PageNotFoundException::class);
        $controller->resolveForTest();
    }

    public function testUnpublishedAliasThrowsPageNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'alias = :alias')
                    && str_contains($sql, 'published = :published')),
                ['alias' => 'draft', 'published' => '1'],
                self::anything(),
            )
            ->willReturn(false);
        [$controller] = $this->createController('draft', new QnaSessionGateway($connection));

        $this->expectException(PageNotFoundException::class);
        $controller->resolveForTest();
    }

    public function testPublishedAliasIsResolvedAndTheItemIsMarkedAsUsed(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())
            ->method('findPublishedByAlias')
            ->with('mobility')
            ->willReturn($session);
        [$controller, $input] = $this->createController('mobility', $gateway);

        self::assertSame($session, $controller->resolveForTest());
        self::assertSame('auto_item', $input->requestedKey);
        self::assertFalse($input->keptUnused);
    }

    public function testListAndReaderCanShareAPageBecauseOnlyTheReaderConsumesTheItem(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())
            ->method('findPublishedByAlias')
            ->with('mobility')
            ->willReturn($session);
        [$controller, $input] = $this->createController('mobility', $gateway);

        $page = $this->createStub(PageModel::class);
        $urlGenerator = $this->createMock(ContentUrlGenerator::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with($page, ['parameters' => '/mobility'])
            ->willReturn('/questions/mobility');

        $items = (new QnaSessionListViewFactory($urlGenerator))->create([$session], $page);

        self::assertSame('/questions/mobility', $items[0]->url);
        self::assertSame($session, $controller->resolveForTest());
        self::assertSame('auto_item', $input->requestedKey);
        self::assertFalse($input->keptUnused);
    }

    /**
     * @return array{TestableQnaSessionReaderController, ReaderInputAdapter}
     */
    private function createController(
        ?string $alias,
        QnaSessionGateway $gateway,
    ): array {
        $input = new ReaderInputAdapter($alias);
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects(self::once())->method('initialize');
        $framework->expects(self::once())
            ->method('getAdapter')
            ->with(Input::class)
            ->willReturn($input);

        return [
            new TestableQnaSessionReaderController($framework, $gateway, new QnaReaderViewFactory()),
            $input,
        ];
    }
}

final class TestableQnaSessionReaderController extends QnaSessionReaderController
{
    public function resolveForTest(): QnaSession
    {
        return $this->resolveSession();
    }
}

/** @extends Adapter<Input> */
final class ReaderInputAdapter extends Adapter
{
    public ?string $requestedKey = null;

    public ?bool $keptUnused = null;

    public function __construct(private readonly ?string $value)
    {
        parent::__construct(Input::class);
    }

    public function get(
        string $key,
        bool $decodeEntities = false,
        bool $keepUnusedRouteParameter = false,
    ): ?string {
        $this->requestedKey = $key;
        $this->keptUnused = $keepUnusedRouteParameter;

        return $this->value;
    }
}
