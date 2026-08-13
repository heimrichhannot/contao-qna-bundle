<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Controller;

use Contao\CoreBundle\Exception\PageNotFoundException;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Exception\AuthenticationRequiredException;
use HeimrichHannot\QnaBundle\Exception\EmptyQuestionException;
use HeimrichHannot\QnaBundle\Exception\InvalidSessionTransitionException;
use HeimrichHannot\QnaBundle\Exception\QuestionCooldownException;
use HeimrichHannot\QnaBundle\Exception\QuestionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\QuestionTooLongException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter;
use HeimrichHannot\QnaBundle\Service\QuestionService;
use HeimrichHannot\QnaBundle\Service\SessionService;
use HeimrichHannot\QnaBundle\Service\VoteService;
use HeimrichHannot\QnaBundle\View\QnaFrameResponseFactory;
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
        private QnaFrameResponseFactory $responseFactory,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
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
        } catch (AuthenticationRequiredException) {
            return $this->responseFactory->renderReaderControls(
                $sessionId,
                'qna.error.authentication_required',
                Response::HTTP_UNAUTHORIZED,
                $question,
            );
        } catch (EmptyQuestionException) {
            return $this->responseFactory->renderReaderControls(
                $sessionId,
                'qna.error.empty_question',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $question,
            );
        } catch (QuestionTooLongException) {
            return $this->responseFactory->renderReaderControls(
                $sessionId,
                'qna.error.question_too_long',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $question,
            );
        } catch (QuestionCooldownException) {
            return $this->responseFactory->renderReaderControls(
                $sessionId,
                'qna.error.question_cooldown',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $question,
            );
        } catch (SessionNotOpenException) {
            return $this->responseFactory->renderReaderControls(
                $sessionId,
                'qna.error.session_not_open',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $question,
            );
        } catch (SessionNotFoundException|SessionNotPublishedException) {
            throw new PageNotFoundException();
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
            $this->voteService->vote($questionId, $sessionId);
        } catch (AuthenticationRequiredException) {
            return $this->responseFactory->renderReaderQuestions(
                $sessionId,
                'qna.error.authentication_required',
                Response::HTTP_UNAUTHORIZED,
            );
        } catch (SessionNotOpenException) {
            return $this->responseFactory->renderReaderQuestions(
                $sessionId,
                'qna.error.session_not_open',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (QuestionNotFoundException|SessionNotFoundException|SessionNotPublishedException) {
            throw new PageNotFoundException();
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
        $sort = $request->query->getString('sort', 'votes');

        try {
            $this->sessionService->start($session->id);
        } catch (InvalidSessionTransitionException) {
            return $this->responseFactory->renderStage(
                $session->id,
                $sort,
                'qna.error.invalid_transition',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (SessionNotFoundException|SessionNotPublishedException) {
            throw new PageNotFoundException();
        }

        return $this->redirectToRoute('contao_qna_stage_questions', [
            'sessionId' => $session->id,
            'sort' => $sort,
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
        $sort = $request->query->getString('sort', 'votes');

        try {
            $this->sessionService->stop($session->id);
        } catch (InvalidSessionTransitionException) {
            return $this->responseFactory->renderStage(
                $session->id,
                $sort,
                'qna.error.invalid_transition',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (SessionNotFoundException|SessionNotPublishedException) {
            throw new PageNotFoundException();
        }

        return $this->redirectToRoute('contao_qna_stage_questions', [
            'sessionId' => $session->id,
            'sort' => $sort,
        ]);
    }

    private function requireControl(int $sessionId): QnaSession
    {
        $session = $this->sessionGateway->findPublished($sessionId);

        if (!$session instanceof QnaSession) {
            throw new PageNotFoundException();
        }

        if (!$this->security->isGranted(QnaSessionControlVoter::ATTRIBUTE, $session)) {
            throw new AccessDeniedHttpException();
        }

        return $session;
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
