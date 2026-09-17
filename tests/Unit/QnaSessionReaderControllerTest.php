<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\ContentModel;
use Contao\CoreBundle\Cache\CacheTagManager;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Controller\ContentElement\QnaSessionReaderController;
use HeimrichHannot\QnaBundle\Domain\Session;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Service\PollingPolicy;
use HeimrichHannot\QnaBundle\View\QnaSessionListViewFactory;
use HeimrichHannot\QnaBundle\View\ReaderViewFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        $controller->resolveForTest($this->contentModel(0));
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
        $controller->resolveForTest($this->contentModel(0));
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
        $controller->resolveForTest($this->contentModel(0));
    }

    public function testPublishedAliasIsResolvedAndTheItemIsMarkedAsUsed(): void
    {
        $session = new Session(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())
            ->method('findPublishedByAlias')
            ->with('mobility')
            ->willReturn($session);
        [$controller, $input] = $this->createController('mobility', $gateway);

        self::assertSame($session, $controller->resolveForTest($this->contentModel(0)));
        self::assertSame('auto_item', $input->requestedKey);
        self::assertFalse($input->keptUnused);
    }

    public function testListAndReaderCanShareAPageBecauseOnlyTheReaderConsumesTheItem(): void
    {
        $session = new Session(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
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
        self::assertSame($session, $controller->resolveForTest($this->contentModel(0)));
        self::assertSame('auto_item', $input->requestedKey);
        self::assertFalse($input->keptUnused);
    }

    /** @return iterable<string, array{int|string, string|null}> */
    public static function configuredSessionProvider(): iterable
    {
        yield 'integer without item' => [7, null];
        yield 'database string with unrelated item' => ['7', 'another-session'];
    }

    #[DataProvider('configuredSessionProvider')]
    public function testConfiguredSessionNeverAccessesInput(int|string $configured, ?string $alias): void
    {
        $session = new Session(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('findPublished')->with(7)->willReturn($session);
        $gateway->expects(self::never())->method('findPublishedByAlias');
        [$controller, $input] = $this->createController($alias, $gateway, true);

        self::assertSame($session, $controller->resolveForTest($this->contentModel($configured)));
        self::assertNull($input->requestedKey);
    }

    public function testUnavailableConfiguredSessionReturnsEmptyTaggedResponse(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::callback(static fn (string $sql): bool => str_contains($sql, 'id = :id')
                && str_contains($sql, 'published = :published')),
            ['id' => 7, 'published' => '1'],
            self::anything(),
        )->willReturn(false);
        [$controller] = $this->createController('mobility', new QnaSessionGateway($connection), true);
        $container = new Container();
        $scope = $this->createStub(ScopeMatcher::class);
        $scope->method('isBackendRequest')->willReturn(false);
        $container->set('contao.routing.scope_matcher', $scope);
        $tags = $this->createMock(CacheTagManager::class);
        $tags->expects(self::once())->method('tagWith')->with('contao.db.tl_qna_session.7');
        $container->set('contao.cache.tag_manager', $tags);
        $controller->setContainer($container);
        $template = new FragmentTemplate('reader', static function (): Response {
            self::fail('An unavailable configured session must not render the template.');
        });

        $response = $controller->responseForTest($template, $this->contentModel(7));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testEditorReceivesUnpublishedConfiguredSessionTitle(): void
    {
        $session = new Session(7, 'Draft session', 'draft', false, SessionState::WAITING, null, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('find')->with(7)->willReturn($session);
        $gateway->expects(self::never())->method('findPublished');
        [$controller] = $this->createController(null, $gateway, true);
        $container = new Container();
        $scope = $this->createStub(ScopeMatcher::class);
        $scope->method('isBackendRequest')->willReturn(true);
        $container->set('contao.routing.scope_matcher', $scope);
        $controller->setContainer($container);
        $template = new FragmentTemplate('reader', static fn (): Response => new Response('editor'));

        self::assertSame('editor', $controller->responseForTest($template, $this->contentModel(7))->getContent());
        self::assertSame('Draft session', $template->get('editor_session_title'));
        self::assertNull($template->getData()['view']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function unconfiguredSessionProvider(): iterable
    {
        yield 'missing field' => [[]];
        foreach (['', '0', 0, null, false, [], 1.5, -1] as $index => $value) {
            yield 'empty or invalid '.$index => [['qnaSession' => $value]];
        }
    }

    /** @param array<string, mixed> $row */
    #[DataProvider('unconfiguredSessionProvider')]
    public function testEmptyOrInvalidConfigurationUsesAlias(array $row): void
    {
        $session = new Session(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::never())->method('findPublished');
        $gateway->expects(self::once())->method('findPublishedByAlias')->with('mobility')->willReturn($session);
        [$controller, $input] = $this->createController('mobility', $gateway);
        $model = $this->createStub(ContentModel::class);
        $model->method('row')->willReturn($row);

        self::assertSame($session, $controller->resolveForTest($model));
        self::assertSame('auto_item', $input->requestedKey);
        self::assertFalse($input->keptUnused);
    }

    private function contentModel(int|string $configured): ContentModel
    {
        $model = $this->createStub(ContentModel::class);
        $model->method('row')->willReturn(['qnaSession' => $configured]);

        return $model;
    }

    /**
     * @return array{TestableQnaSessionReaderController, ReaderInputAdapter}
     */
    private function createController(
        ?string $alias,
        QnaSessionGateway $gateway,
        bool $configured = false,
    ): array {
        $input = new ReaderInputAdapter($alias);
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($configured ? self::never() : self::once())->method('initialize');
        $framework->expects($configured ? self::never() : self::once())
            ->method('getAdapter')
            ->with(Input::class)
            ->willReturn($input);

        return [
            new TestableQnaSessionReaderController(
                $framework,
                $gateway,
                (new \ReflectionClass(ReaderViewFactory::class))->newInstanceWithoutConstructor(),
                $this->createStub(UrlGeneratorInterface::class),
                (new \ReflectionClass(PollingPolicy::class))->newInstanceWithoutConstructor(),
            ),
            $input,
        ];
    }
}

final class TestableQnaSessionReaderController extends QnaSessionReaderController
{
    public function resolveForTest(ContentModel $model): ?Session
    {
        return $this->resolveSession($model);
    }

    public function responseForTest(FragmentTemplate $template, ContentModel $model): Response
    {
        return $this->getResponse($template, $model, new Request());
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
