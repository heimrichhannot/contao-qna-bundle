<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\ContentComposition\ContentComposition;
use Contao\CoreBundle\ContentComposition\ContentCompositionBuilder;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Twig\LayoutTemplate;
use Contao\FrontendIndex;
use Contao\FrontendTemplate;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use HeimrichHannot\QnaBundle\Controller\Page\QnaStageController;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class QnaStageControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $hooks = $GLOBALS['TL_HOOKS'] ?? [];

        if (\is_array($hooks) && \is_array($hooks['generatePage'] ?? null)) {
            unset($hooks['generatePage']['qna_stage']);
            $GLOBALS['TL_HOOKS'] = $hooks;
        }
    }

    public function testModernLayoutPlacesStageContentInTheMainSlot(): void
    {
        $page = $this->createPage();
        $layout = $this->createLayout('modern');
        $framework = $this->createFramework($layout);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('findAllPublished')->willReturn([]);
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('@Contao/qna/stage_overview.html.twig', ['sessions' => []])
            ->willReturn('<section>Stage</section>');

        $layoutTemplate = new LayoutTemplate(
            'layout/modern',
            static function (LayoutTemplate $template, ?Response $response): Response {
                $content = $template->getSlot('main');
                self::assertIsString($content);

                return new Response($content);
            },
        );
        $builder = $this->createMock(ContentCompositionBuilder::class);
        $builder->expects(self::once())->method('buildLayoutTemplate')->willReturn($layoutTemplate);
        $composition = $this->createMock(ContentComposition::class);
        $composition->expects(self::once())
            ->method('createContentCompositionBuilder')
            ->with($page)
            ->willReturn($builder);
        $contextAccessor = $this->createMock(ResponseContextAccessor::class);
        $contextAccessor->expects(self::once())->method('finalizeCurrentContext');

        $controller = new TestableQnaStageController(
            $composition,
            $framework,
            $contextAccessor,
            $gateway,
            $this->createStub(UrlGeneratorInterface::class),
            $twig,
            2500,
        );

        $response = $controller->renderForTest($page, '', 'votes');

        self::assertSame('<section>Stage</section>', $response->getContent());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function testLegacyLayoutUsesTemporaryGeneratePageHookAndRemovesIt(): void
    {
        $page = $this->createPage();
        $layout = $this->createLayout('default');
        $frontendIndex = $this->createMock(FrontendIndex::class);
        $framework = $this->createFramework($layout, $frontendIndex);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('findAllPublished')->willReturn([]);
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->willReturn('<section>Legacy stage</section>');

        $controller = new TestableQnaStageController(
            $this->createStub(ContentComposition::class),
            $framework,
            $this->createStub(ResponseContextAccessor::class),
            $gateway,
            $this->createStub(UrlGeneratorInterface::class),
            $twig,
            2500,
        );

        $frontendIndex->expects(self::once())
            ->method('renderPage')
            ->with($page)
            ->willReturnCallback(function () use ($controller, $page, $layout): Response {
                $generatePageHooks = self::getGeneratePageHooks();
                self::assertSame(
                    [QnaStageController::class, 'renderPageContent'],
                    $generatePageHooks['qna_stage'],
                );

                $pageRegular = $this->createStub(PageRegular::class);
                $pageRegular->Template = new ControllerTestFrontendTemplate();
                $controller->renderPageContent($page, $layout, $pageRegular);

                self::assertSame('<section>Legacy stage</section>', $pageRegular->Template->__get('main'));

                return new Response('<html>Legacy</html>');
            });

        $response = $controller->renderForTest($page, '', 'votes');

        self::assertSame('<html>Legacy</html>', $response->getContent());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertArrayNotHasKey('qna_stage', self::getGeneratePageHooks());
    }

    public function testPublishedAliasRendersTheStageDetailShell(): void
    {
        $page = $this->createPage();
        $layout = $this->createLayout('modern');
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())
            ->method('findPublishedByAlias')
            ->with('mobility')
            ->willReturn($session);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('contao_qna_stage_questions', ['sessionId' => 7, 'sort' => 'time'])
            ->willReturn('/_qna/stage/7/questions?sort=time');
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@Contao/qna/stage_detail.html.twig',
                self::callback(static fn (array $context): bool => $session === $context['session']
                    && 'qna-session-7-stage' === $context['frame_id']
                    && '/_qna/stage/7/questions?sort=time' === $context['frame_src']),
            )
            ->willReturn('<section>Detail</section>');

        $layoutTemplate = new LayoutTemplate(
            'layout/modern',
            static function (LayoutTemplate $template, ?Response $response): Response {
                $content = $template->getSlot('main');
                self::assertIsString($content);

                return new Response($content);
            },
        );
        $builder = $this->createStub(ContentCompositionBuilder::class);
        $builder->method('buildLayoutTemplate')->willReturn($layoutTemplate);
        $composition = $this->createStub(ContentComposition::class);
        $composition->method('createContentCompositionBuilder')->willReturn($builder);
        $controller = new TestableQnaStageController(
            $composition,
            $this->createFramework($layout),
            $this->createStub(ResponseContextAccessor::class),
            $gateway,
            $urlGenerator,
            $twig,
            2500,
        );

        $response = $controller->renderForTest($page, 'mobility', 'time');

        self::assertSame('<section>Detail</section>', $response->getContent());
    }

    public function testUnknownStageAliasThrowsPageNotFound(): void
    {
        $page = $this->createPage();
        $layout = $this->createLayout('modern');
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())
            ->method('findPublishedByAlias')
            ->with('unknown')
            ->willReturn(null);

        $layoutTemplate = new LayoutTemplate(
            'layout/modern',
            static fn (LayoutTemplate $template, ?Response $response): Response => new Response(),
        );
        $builder = $this->createStub(ContentCompositionBuilder::class);
        $builder->method('buildLayoutTemplate')->willReturn($layoutTemplate);
        $composition = $this->createStub(ContentComposition::class);
        $composition->method('createContentCompositionBuilder')->willReturn($builder);
        $controller = new TestableQnaStageController(
            $composition,
            $this->createFramework($layout),
            $this->createStub(ResponseContextAccessor::class),
            $gateway,
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(Environment::class),
            2500,
        );

        $this->expectException(PageNotFoundException::class);
        $controller->renderForTest($page, 'unknown', 'votes');
    }

    private function createPage(): PageModel
    {
        $page = new ControllerTestPageModel();
        $page->layout = 5;
        $page->cache = 0;
        $page->clientCache = 0;

        return $page;
    }

    private function createLayout(string $type): LayoutModel
    {
        $layout = new ControllerTestLayoutModel();
        $layout->type = $type;

        return $layout;
    }

    private function createFramework(LayoutModel $layout, ?FrontendIndex $frontendIndex = null): ContaoFramework
    {
        $adapter = new StageLayoutModelAdapter($layout);
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects(self::once())->method('initialize');
        $framework->expects(self::once())
            ->method('getAdapter')
            ->with(LayoutModel::class)
            ->willReturn($adapter);

        if ($frontendIndex) {
            $framework->expects(self::once())
                ->method('createInstance')
                ->with(FrontendIndex::class)
                ->willReturn($frontendIndex);
        }

        return $framework;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function getGeneratePageHooks(): array
    {
        $hooks = $GLOBALS['TL_HOOKS'] ?? [];

        if (!\is_array($hooks)) {
            return [];
        }

        $generatePageHooks = $hooks['generatePage'] ?? [];

        return \is_array($generatePageHooks) ? $generatePageHooks : [];
    }
}

final class ControllerTestPageModel extends PageModel
{
    public int $layout = 0;

    public int $cache = 0;

    public int $clientCache = 0;

    public function __construct()
    {
    }
}

final class ControllerTestLayoutModel extends LayoutModel
{
    public string $type = '';

    public function __construct()
    {
    }
}

final class ControllerTestFrontendTemplate extends FrontendTemplate
{
    public function __construct()
    {
    }
}

final class TestableQnaStageController extends QnaStageController
{
    public function renderForTest(PageModel $pageModel, string $alias, string $sort): Response
    {
        return $this->executeRender($pageModel, ['alias' => $alias, 'sort' => $sort]);
    }
}

/** @extends Adapter<LayoutModel> */
final class StageLayoutModelAdapter extends Adapter
{
    public function __construct(private readonly LayoutModel $layout)
    {
        parent::__construct(LayoutModel::class);
    }

    public function findById(mixed $id): LayoutModel
    {
        return $this->layout;
    }
}
