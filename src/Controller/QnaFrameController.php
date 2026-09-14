<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\PageNotFoundException;
use HeimrichHannot\QnaBundle\Domain\Session;
use HeimrichHannot\QnaBundle\Enum\QuestionSort;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter;
use HeimrichHannot\QnaBundle\Service\PollingPolicy;
use HeimrichHannot\QnaBundle\View\ReaderViewFactory;
use HeimrichHannot\QnaBundle\View\StageViewFactory;
use HeimrichHannot\QnaBundle\View\TurboResponseFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class QnaFrameController
{
    public function __construct(
        private QnaSessionGateway $sessionGateway,
        private ReaderViewFactory $readerViewFactory,
        private StageViewFactory $stageViewFactory,
        private TurboResponseFactory $responseFactory,
        private ContaoCsrfTokenManager $csrfTokenManager,
        private Security $security,
        private PollingPolicy $pollingPolicy,
    ) {
    }

    #[Route(
        '/_qna/reader/{sessionId}',
        name: 'contao_qna_reader_frame',
        requirements: ['sessionId' => '\\d+'],
        methods: ['GET'],
    )]
    public function reader(int $sessionId, Request $request): Response
    {
        $session = $this->requirePublishedSession($sessionId);

        if ($this->acceptsTurboStream($request)) {
            return $this->responseFactory->stream($this->readerViewFactory->renderUpdate(
                $session,
                $request->query->getBoolean('resetQuestionForm'),
            ));
        }

        return $this->responseFactory->html($this->readerViewFactory->renderQuestions($session));
    }

    #[Route(
        '/_qna/reader/{sessionId}/controls',
        name: 'contao_qna_reader_controls',
        requirements: ['sessionId' => '\\d+'],
        methods: ['GET'],
    )]
    public function readerControls(int $sessionId): Response
    {
        $session = $this->requirePublishedSession($sessionId);

        return $this->responseFactory->html($this->readerViewFactory->renderControls($session));
    }

    #[Route(
        '/_qna/stage/{sessionId}/questions',
        name: 'contao_qna_stage_questions',
        requirements: ['sessionId' => '\\d+'],
        methods: ['GET'],
    )]
    public function stage(int $sessionId, Request $request): Response
    {
        $session = $this->requirePublishedSession($sessionId);
        $sort = QuestionSort::fromRequestValue($request->query->getString('sort'));
        $view = $this->stageViewFactory->create(
            $session,
            $sort,
            $this->security->isGranted(QnaSessionControlVoter::ATTRIBUTE, $session),
        );
        $requestToken = $view->showStartButton || $view->showStopButton
            ? $this->csrfTokenManager->getDefaultTokenValue()
            : null;
        $pollingInterval = $this->pollingPolicy->intervalFor($session->state);

        return $this->acceptsTurboStream($request)
            ? $this->responseFactory->stream($this->stageViewFactory->renderUpdate($view, $requestToken, $pollingInterval))
            : $this->responseFactory->html($this->stageViewFactory->renderFrame($view, $requestToken, $pollingInterval));
    }

    private function requirePublishedSession(int $sessionId): Session
    {
        $session = $this->sessionGateway->findPublished($sessionId);

        if (!$session instanceof Session) {
            throw new PageNotFoundException();
        }

        return $session;
    }

    private function acceptsTurboStream(Request $request): bool
    {
        return str_contains(
            $request->headers->get('Accept', ''),
            TurboResponseFactory::TURBO_STREAM_CONTENT_TYPE,
        );
    }
}
