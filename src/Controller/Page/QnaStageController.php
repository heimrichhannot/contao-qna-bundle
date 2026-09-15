<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Controller\Page;

use Contao\CoreBundle\ContentComposition\ContentComposition;
use Contao\CoreBundle\Controller\Page\AbstractPageController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsPage;
use Contao\CoreBundle\EventListener\SubrequestCacheSubscriber;
use Contao\CoreBundle\Exception\NoLayoutSpecifiedException;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\FrontendIndex;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use HeimrichHannot\EncoreContracts\PageAssetsTrait;
use HeimrichHannot\QnaBundle\Asset\EncoreExtension;
use HeimrichHannot\QnaBundle\Domain\Session;
use HeimrichHannot\QnaBundle\Enum\QuestionSort;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Service\PollingPolicy;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[AsPage(type: 'qna_stage', path: '{alias}', defaults: ['alias' => ''], contentComposition: false)]
class QnaStageController extends AbstractPageController
{
    use PageAssetsTrait;

    /** @var array{alias: string, sort: QuestionSort}|null */
    private ?array $legacyArguments = null;

    public function __construct(
        private readonly ContentComposition $contentComposition,
        private readonly ContaoFramework $framework,
        private readonly ResponseContextAccessor $responseContextAccessor,
        private readonly QnaSessionGateway $sessionGateway,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly PollingPolicy $pollingPolicy,
    ) {
    }

    public function __invoke(PageModel $pageModel, Request $request, string $alias = ''): Response
    {
        return $this->executeRender($pageModel, [
            'alias' => $alias,
            'sort' => QuestionSort::fromRequestValue($request->query->getString('sort')),
        ]);
    }

    /**
     * @param array{alias: string, sort: QuestionSort} $arguments
     */
    protected function executeRender(PageModel $pageModel, array $arguments): Response
    {
        $this->framework->initialize();
        $layout = $this->framework->getAdapter(LayoutModel::class)->findById($pageModel->layout);

        if (!$layout instanceof LayoutModel) {
            throw new NoLayoutSpecifiedException('No layout specified');
        }

        $response = match ($layout->type) {
            'modern' => $this->renderModern($pageModel, $arguments),
            'default' => $this->renderLegacy($pageModel, $arguments),
            default => throw new \LogicException(\sprintf('Unknown layout type "%s".', $layout->type)),
        };

        // The overview contains the current session state and must never enter a shared cache.
        $response->setPrivate();

        return $response;
    }

    public function renderPageContent(
        PageModel $pageModel,
        LayoutModel $layout,
        PageRegular $pageRegular,
    ): void {
        if (null === $this->legacyArguments) {
            throw new \LogicException('The Q&A stage legacy render was not initialized.');
        }

        $pageRegular->Template->__set('main', $this->getContent($pageModel, $this->legacyArguments));
    }

    /**
     * @param array{alias: string, sort: QuestionSort} $arguments
     */
    private function renderModern(PageModel $pageModel, array $arguments): Response
    {
        $layoutTemplate = $this->contentComposition
            ->createContentCompositionBuilder($pageModel)
            ->buildLayoutTemplate()
        ;

        $layoutTemplate->setSlot('main', $this->getContent($pageModel, $arguments));
        $response = $layoutTemplate->getResponse();

        $this->responseContextAccessor->finalizeCurrentContext($response);
        $response->headers->set(SubrequestCacheSubscriber::MERGE_CACHE_HEADER, '1');

        return $this->setCacheHeaders($response, $pageModel);
    }

    /**
     * Renders the stage inside a legacy ("default") page layout.
     *
     * This deliberately breaks the AGENTS.md rule against writing to
     * $GLOBALS['TL_HOOKS']: registering a PageRegular subclass in
     * $GLOBALS['TL_PTY'] would avoid both the runtime hook and the mutable
     * $legacyArguments field, but Contao 6 removes FrontendIndex::renderLegacy()
     * and PageRegular anyway, so that rewrite was deferred rather than done
     * twice. See B16 in .docs/refactor/REFACTOR.md for the researched
     * alternative and the reasoning. Do not copy this pattern elsewhere.
     *
     * @param array{alias: string, sort: QuestionSort} $arguments
     */
    private function renderLegacy(PageModel $pageModel, array $arguments): Response
    {
        $hookKey = 'qna_stage';
        $generatePageHooks = $this->getGeneratePageHooks();
        $hadPreviousHook = \array_key_exists($hookKey, $generatePageHooks);
        $previousHook = $generatePageHooks[$hookKey] ?? null;

        $this->legacyArguments = $arguments;
        $generatePageHooks[$hookKey] = [self::class, 'renderPageContent'];
        $this->setGeneratePageHooks($generatePageHooks);

        try {
            $frontendIndex = $this->framework->createInstance(FrontendIndex::class);

            if (!$frontendIndex instanceof FrontendIndex) {
                throw new \LogicException('Could not create the Contao front end controller.');
            }

            return $frontendIndex->renderPage($pageModel);
        } finally {
            $this->legacyArguments = null;
            $generatePageHooks = $this->getGeneratePageHooks();

            if ($hadPreviousHook) {
                $generatePageHooks[$hookKey] = $previousHook;
            } else {
                unset($generatePageHooks[$hookKey]);
            }

            $this->setGeneratePageHooks($generatePageHooks);
        }
    }

    /**
     * @param array{alias: string, sort: QuestionSort} $arguments
     */
    private function getContent(PageModel $pageModel, array $arguments): string
    {
        // Encore collects entrypoints in the response context, which only exists
        // once rendering has started - activating them earlier is a silent no-op.
        $this->addPageEntrypoint(EncoreExtension::ENTRY);

        if ('' === $arguments['alias']) {
            return $this->twig->render('@Contao/qna/stage_overview.html.twig', [
                'sessions' => array_map(
                    fn (Session $session): array => [
                        'id' => $session->id,
                        'title' => $session->title,
                        'state' => $session->state->value,
                        'status_translation_key' => 'qna.stage.status.'.$session->state->value,
                        'url' => $this->generateStageUrl($pageModel, $session->alias),
                    ],
                    $this->sessionGateway->findAllPublished(),
                ),
            ]);
        }

        $session = $this->sessionGateway->findPublishedByAlias($arguments['alias']);

        if (!$session instanceof Session) {
            throw new PageNotFoundException();
        }

        return $this->twig->render('@Contao/qna/stage_detail.html.twig', [
            'session' => $session,
            'frame_id' => \sprintf('qna-session-%d-stage', $session->id),
            'frame_src' => $this->urlGenerator->generate('contao_qna_stage_questions', [
                'sessionId' => $session->id,
                'sort' => $arguments['sort']->value,
            ]),
            'polling_interval' => $this->pollingPolicy->baseInterval(),
            'polling_max_interval' => $this->pollingPolicy->maxInterval(),
        ]);
    }

    private function generateStageUrl(PageModel $pageModel, string $alias): string
    {
        return $this->urlGenerator->generate(PageRoute::PAGE_BASED_ROUTE_NAME, [
            RouteObjectInterface::CONTENT_OBJECT => $pageModel,
            'alias' => $alias,
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getGeneratePageHooks(): array
    {
        $hooks = $GLOBALS['TL_HOOKS'] ?? [];

        if (!\is_array($hooks)) {
            return [];
        }

        $generatePageHooks = $hooks['generatePage'] ?? [];

        return \is_array($generatePageHooks) ? $generatePageHooks : [];
    }

    /**
     * @param array<array-key, mixed> $generatePageHooks
     */
    private function setGeneratePageHooks(array $generatePageHooks): void
    {
        $hooks = $GLOBALS['TL_HOOKS'] ?? [];

        if (!\is_array($hooks)) {
            $hooks = [];
        }

        $hooks['generatePage'] = $generatePageHooks;
        $GLOBALS['TL_HOOKS'] = $hooks;
    }
}
