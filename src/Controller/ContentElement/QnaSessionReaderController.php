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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsContentElement(type: 'qna_session_reader', category: 'qna')]
class QnaSessionReaderController extends AbstractContentElementController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly QnaSessionGateway $sessionGateway,
        private readonly QnaReaderViewFactory $viewFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly int $pollingInterval,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        if ($this->isBackendScope($request)) {
            $template->set('view', null);

            return $template->getResponse();
        }

        $session = $this->resolveSession();
        $this->tagResponse('contao.db.tl_qna_session.'.$session->id);

        $template->set('view', $this->viewFactory->createInitial($session));
        $template->set('controls_frame_src', $this->urlGenerator->generate('contao_qna_reader_controls', [
            'sessionId' => $session->id,
        ]));
        $template->set('questions_frame_src', $this->urlGenerator->generate('contao_qna_reader_frame', [
            'sessionId' => $session->id,
        ]));
        $template->set('polling_interval', $this->pollingInterval);
        $template->set('polling_max_interval', $this->pollingInterval * 16);

        return $template->getResponse();
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
}
