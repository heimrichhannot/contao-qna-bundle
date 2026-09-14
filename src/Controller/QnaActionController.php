<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\PageNotFoundException;
use HeimrichHannot\QnaBundle\Domain\Session;
use HeimrichHannot\QnaBundle\Enum\QuestionSort;
use HeimrichHannot\QnaBundle\Exception\QnaDomainException;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter;
use HeimrichHannot\QnaBundle\Service\PollingPolicy;
use HeimrichHannot\QnaBundle\Service\QuestionAnswerService;
use HeimrichHannot\QnaBundle\Service\QuestionService;
use HeimrichHannot\QnaBundle\Service\SessionService;
use HeimrichHannot\QnaBundle\Service\VoteService;
use HeimrichHannot\QnaBundle\View\ReaderViewFactory;
use HeimrichHannot\QnaBundle\View\StageViewFactory;
use HeimrichHannot\QnaBundle\View\TurboResponseFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class QnaActionController
{
    public function __construct(
        private QuestionService $questionService,
        private VoteService $voteService,
        private SessionService $sessionService,
        private QnaSessionGateway $sessionGateway,
        private ReaderViewFactory $readerViewFactory,
        private StageViewFactory $stageViewFactory,
        private TurboResponseFactory $responseFactory,
        private ContaoCsrfTokenManager $csrfTokenManager,
        private PollingPolicy $pollingPolicy,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private QuestionAnswerService $answerService,
    ) {
    }

    #[Route(
        '/_qna/session/{sessionId}/question',
        name: 'contao_qna_question_create',
        requirements: ['sessionId' => '\\d+'],
        defaults: ['_token_check' => true],
        methods: ['POST'],
    )]
    public function question(int $sessionId, Request $request): Response
    {
        $question = $request->request->getString('question');

        try {
            $this->questionService->create($sessionId, $question);
        } catch (QnaDomainException $exception) {
            $this->throwIfNotFound($exception);

            return $this->responseFactory->html(
                $this->readerViewFactory->renderControls(
                    $this->requirePublishedSession($sessionId),
                    $exception->translationKey(),
                    $question,
                ),
                $exception->statusCode(),
            );
        }

        return $this->redirectToRoute('contao_qna_reader_frame', [
            'sessionId' => $sessionId,
            'resetQuestionForm' => 1,
        ]);
    }

    #[Route(
        '/_qna/session/{sessionId}/question/{questionId}/vote',
        name: 'contao_qna_vote_create',
        requirements: ['sessionId' => '\\d+', 'questionId' => '\\d+'],
        defaults: ['_token_check' => true],
        methods: ['POST'],
    )]
    public function vote(int $sessionId, int $questionId): Response
    {
        try {
            $this->voteService->vote($sessionId, $questionId);
        } catch (QnaDomainException $exception) {
            $this->throwIfNotFound($exception);

            return $this->responseFactory->html(
                $this->readerViewFactory->renderQuestions(
                    $this->requirePublishedSession($sessionId),
                    $exception->translationKey(),
                ),
                $exception->statusCode(),
            );
        }

        return $this->redirectToRoute('contao_qna_reader_frame', ['sessionId' => $sessionId]);
    }

    #[Route(
        '/_qna/session/{sessionId}/start',
        name: 'contao_qna_session_start',
        requirements: ['sessionId' => '\\d+'],
        defaults: ['_token_check' => true],
        methods: ['POST'],
    )]
    public function start(int $sessionId, Request $request): Response
    {
        $session = $this->requireControl($sessionId);
        $sort = QuestionSort::fromRequestValue($request->query->getString('sort'));

        try {
            $this->sessionService->start($session->id);
        } catch (QnaDomainException $exception) {
            $this->throwIfNotFound($exception);

            return $this->renderStage(
                $session->id,
                $sort,
                $exception->translationKey(),
                $exception->statusCode(),
            );
        }

        return $this->redirectToRoute('contao_qna_stage_questions', [
            'sessionId' => $session->id,
            'sort' => $sort->value,
        ]);
    }

    #[Route(
        '/_qna/session/{sessionId}/stop',
        name: 'contao_qna_session_stop',
        requirements: ['sessionId' => '\\d+'],
        defaults: ['_token_check' => true],
        methods: ['POST'],
    )]
    public function stop(int $sessionId, Request $request): Response
    {
        $session = $this->requireControl($sessionId);
        $sort = QuestionSort::fromRequestValue($request->query->getString('sort'));

        try {
            $this->sessionService->stop($session->id);
        } catch (QnaDomainException $exception) {
            $this->throwIfNotFound($exception);

            return $this->renderStage(
                $session->id,
                $sort,
                $exception->translationKey(),
                $exception->statusCode(),
            );
        }

        return $this->redirectToRoute('contao_qna_stage_questions', [
            'sessionId' => $session->id,
            'sort' => $sort->value,
        ]);
    }

    #[Route(
        '/_qna/session/{sessionId}/question/{questionId}/answered',
        name: 'contao_qna_question_answered',
        requirements: ['sessionId' => '\\d+', 'questionId' => '\\d+'],
        defaults: ['_token_check' => true],
        methods: ['POST'],
    )]
    public function answered(int $sessionId, int $questionId, Request $request): Response
    {
        return $this->changeAnswered($sessionId, $questionId, true, $request);
    }

    #[Route(
        '/_qna/session/{sessionId}/question/{questionId}/unanswered',
        name: 'contao_qna_question_unanswered',
        requirements: ['sessionId' => '\\d+', 'questionId' => '\\d+'],
        defaults: ['_token_check' => true],
        methods: ['POST'],
    )]
    public function unanswered(int $sessionId, int $questionId, Request $request): Response
    {
        return $this->changeAnswered($sessionId, $questionId, false, $request);
    }

    private function changeAnswered(int $sessionId, int $questionId, bool $answered, Request $request): Response
    {
        $this->requireControl($sessionId);
        $sort = QuestionSort::fromRequestValue($request->query->getString('sort'));

        try {
            $this->answerService->setAnswered($sessionId, $questionId, $answered);
        } catch (QnaDomainException $exception) {
            $this->throwIfNotFound($exception);

            return $this->renderStage($sessionId, $sort, $exception->translationKey(), $exception->statusCode());
        }

        return $this->redirectToRoute('contao_qna_stage_questions', ['sessionId' => $sessionId, 'sort' => $sort->value]);
    }

    private function renderStage(
        int $sessionId,
        QuestionSort $sort,
        ?string $errorTranslationKey = null,
        int $statusCode = Response::HTTP_OK,
    ): Response {
        $session = $this->requirePublishedSession($sessionId);
        $view = $this->stageViewFactory->create(
            $session,
            $sort,
            $this->security->isGranted(QnaSessionControlVoter::ATTRIBUTE, $session),
            $errorTranslationKey,
        );
        $requestToken = $view->showStartButton || $view->showStopButton
            ? $this->csrfTokenManager->getDefaultTokenValue()
            : null;

        return $this->responseFactory->html(
            $this->stageViewFactory->renderFrame(
                $view,
                $requestToken,
                $this->pollingPolicy->intervalFor($session->state),
            ),
            $statusCode,
        );
    }

    private function requirePublishedSession(int $sessionId): Session
    {
        $session = $this->sessionGateway->findPublished($sessionId);

        if (!$session instanceof Session) {
            throw new PageNotFoundException();
        }

        return $session;
    }

    private function requireControl(int $sessionId): Session
    {
        $session = $this->requirePublishedSession($sessionId);

        if (!$this->security->isGranted(QnaSessionControlVoter::ATTRIBUTE, $session)) {
            throw new AccessDeniedHttpException();
        }

        return $session;
    }

    private function throwIfNotFound(QnaDomainException $exception): void
    {
        if (Response::HTTP_NOT_FOUND === $exception->statusCode()) {
            throw new PageNotFoundException();
        }
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function redirectToRoute(string $route, array $parameters): Response
    {
        return new Response('', Response::HTTP_SEE_OTHER, [
            'Cache-Control' => 'private, no-store',
            'Location' => $this->urlGenerator->generate($route, $parameters),
        ]);
    }
}
