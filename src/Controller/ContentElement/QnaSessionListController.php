<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\PageModel;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\View\QnaSessionListViewFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(type: 'qna_session_list', category: 'qna')]
class QnaSessionListController extends AbstractContentElementController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly QnaSessionGateway $sessionGateway,
        private readonly QnaSessionListViewFactory $viewFactory,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();

        $jumpTo = $model->row()['jumpTo'] ?? null;

        if (!\is_int($jumpTo) && !\is_string($jumpTo)) {
            throw new \RuntimeException('The Q&A session list requires a valid reader page.');
        }

        $page = $this->framework
            ->getAdapter(PageModel::class)
            ->findByPk((int) $jumpTo)
        ;

        if (!$page instanceof PageModel) {
            throw new \RuntimeException('The Q&A session list requires a valid reader page.');
        }

        $this->tagResponse(['contao.db.tl_qna_session', $page]);

        return $this->render('@HeimrichHannotQna/content_element/qna_session_list.html.twig', [
            ...$this->templateContext($request),
            'sessions' => $this->viewFactory->create($this->sessionGateway->findAllPublished(), $page),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function templateContext(Request $request): array
    {
        return [
            'type' => 'qna_session_list',
            'element_html_id' => null,
            'element_css_classes' => '',
            'headline' => ['text' => '', 'tag_name' => 'h2'],
            'as_editor_view' => $this->isBackendScope($request),
        ];
    }
}
