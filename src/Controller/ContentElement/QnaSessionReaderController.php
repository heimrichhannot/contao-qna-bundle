<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\View\QnaReaderViewFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(type: 'qna_session_reader', category: 'qna')]
class QnaSessionReaderController extends AbstractContentElementController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly QnaSessionGateway $sessionGateway,
        private readonly QnaReaderViewFactory $viewFactory,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        if ($this->isBackendScope($request)) {
            return $this->render('@HeimrichHannotQna/content_element/qna_session_reader.html.twig', [
                ...$this->templateContext($request),
                'view' => null,
            ]);
        }

        $session = $this->resolveSession();
        $this->tagResponse('contao.db.tl_qna_session.'.$session->id);

        return $this->render('@HeimrichHannotQna/content_element/qna_session_reader.html.twig', [
            ...$this->templateContext($request),
            'view' => $this->viewFactory->createInitial($session),
        ]);
    }

    protected function resolveSession(): QnaSession
    {
        $this->framework->initialize();

        // Input::get() deliberately uses its default third argument (false). This
        // consumes the legacy route parameter so Contao does not reject it later.
        $alias = $this->framework->getAdapter(Input::class)->get('auto_item');

        if (!\is_string($alias) || '' === $alias) {
            throw new PageNotFoundException();
        }

        $session = $this->sessionGateway->findPublishedByAlias($alias);

        if (!$session instanceof QnaSession) {
            throw new PageNotFoundException();
        }

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    private function templateContext(Request $request): array
    {
        return [
            'type' => 'qna_session_reader',
            'element_html_id' => null,
            'element_css_classes' => '',
            'headline' => ['text' => '', 'tag_name' => 'h2'],
            'as_editor_view' => $this->isBackendScope($request),
        ];
    }
}
