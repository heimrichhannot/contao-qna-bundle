<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\PageNotFoundException;
use HeimrichHannot\QnaBundle\Dto\QnaQuestionListItem;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class QnaFrameResponseFactory
{
    public const string TURBO_STREAM_CONTENT_TYPE = 'text/vnd.turbo-stream.html';

    private const int IDLE_INTERVAL_MULTIPLIER = 4;

    public function __construct(
        private Environment $twig,
        private QnaSessionGateway $sessionGateway,
        private QnaQuestionGateway $questionGateway,
        private FrontendMemberProvider $memberProvider,
        private QnaReaderViewFactory $readerViewFactory,
        private ContaoCsrfTokenManager $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
        private int $pollingInterval,
        private int $maxQuestionLength,
    ) {
    }

    public function renderReaderControls(
        int $sessionId,
        ?string $errorTranslationKey = null,
        int $statusCode = Response::HTTP_OK,
        string $questionValue = '',
    ): Response {
        $session = $this->requirePublishedSession($sessionId);

        return $this->createResponse(
            $this->twig->render(
                '@Contao/qna/reader_controls_frame.html.twig',
                $this->createReaderContext($session, false, $errorTranslationKey, $questionValue),
            ),
            $statusCode,
        );
    }

    public function renderReaderQuestions(
        int $sessionId,
        ?string $errorTranslationKey = null,
        int $statusCode = Response::HTTP_OK,
    ): Response {
        $session = $this->requirePublishedSession($sessionId);

        return $this->createResponse(
            $this->twig->render(
                '@Contao/qna/reader_questions_frame.html.twig',
                $this->createReaderContext($session, true, $errorTranslationKey),
            ),
            $statusCode,
        );
    }

    public function renderReaderUpdate(int $sessionId, bool $resetQuestionForm = false): Response
    {
        $session = $this->requirePublishedSession($sessionId);
        $context = $this->createReaderContext($session, true);
        $context['reset_question_form'] = $resetQuestionForm;

        return $this->createStreamResponse(
            $this->twig->render('@Contao/qna/reader_update.stream.html.twig', $context),
        );
    }

    public function renderStage(
        int $sessionId,
        string $sort = 'votes',
        ?string $errorTranslationKey = null,
        int $statusCode = Response::HTTP_OK,
    ): Response {
        $session = $this->requirePublishedSession($sessionId);

        return $this->createResponse(
            $this->twig->render(
                '@Contao/qna/stage_questions.html.twig',
                $this->createStageContext($session, $sort, $errorTranslationKey),
            ),
            $statusCode,
        );
    }

    public function renderStageUpdate(int $sessionId, string $sort = 'votes'): Response
    {
        $session = $this->requirePublishedSession($sessionId);

        return $this->createStreamResponse(
            $this->twig->render(
                '@Contao/qna/stage_update.stream.html.twig',
                $this->createStageContext($session, $sort),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createReaderContext(
        QnaSession $session,
        bool $includeQuestions,
        ?string $errorTranslationKey = null,
        string $questionValue = '',
    ): array {
        $memberId = $this->memberProvider->getIdOrNull();
        $view = $this->readerViewFactory->createDynamic($session, null !== $memberId);
        $questions = $includeQuestions && $view->showQuestions
            ? $this->questionGateway->findForSession($session->id, $memberId ?? 0)
            : [];
        $requestToken = $view->showQuestionForm || ($includeQuestions && $view->showVoteButtons)
            ? $this->csrfTokenManager->getDefaultTokenValue()
            : null;

        return [
            'view' => $view,
            'questions' => $questions,
            'question_form_action' => $this->urlGenerator->generate('contao_qna_question_create', [
                'sessionId' => $session->id,
            ]),
            'vote_urls' => $this->createVoteUrls($questions),
            'request_token' => $requestToken,
            'max_question_length' => $this->maxQuestionLength,
            'polling_interval' => $this->intervalFor($session->state),
            'error_translation_key' => $errorTranslationKey,
            'question_value' => $questionValue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createStageContext(
        QnaSession $session,
        string $sort,
        ?string $errorTranslationKey = null,
    ): array {
        $sort = $this->normalizeSort($sort);
        $showQuestions = SessionState::WAITING !== $session->state;
        $canControl = $this->security->isGranted(QnaSessionControlVoter::ATTRIBUTE, $session);
        $showStartButton = $canControl && SessionState::WAITING === $session->state;
        $showStopButton = $canControl && SessionState::OPEN === $session->state;
        $routeParameters = ['sessionId' => $session->id, 'sort' => $sort];

        return [
            'session' => $session,
            'status_translation_key' => 'qna.stage.status.'.$session->state->value,
            'questions' => $showQuestions
                ? $this->questionGateway->findForStage($session->id, $sort)
                : [],
            'show_questions' => $showQuestions,
            'show_start_button' => $showStartButton,
            'show_stop_button' => $showStopButton,
            'start_url' => $this->urlGenerator->generate('contao_qna_session_start', $routeParameters),
            'stop_url' => $this->urlGenerator->generate('contao_qna_session_stop', $routeParameters),
            'request_token' => $showStartButton || $showStopButton
                ? $this->csrfTokenManager->getDefaultTokenValue()
                : null,
            'frame_id' => \sprintf('qna-session-%d-stage', $session->id),
            'sort' => $sort,
            'sort_votes_url' => $this->urlGenerator->generate('contao_qna_stage_questions', [
                'sessionId' => $session->id,
                'sort' => 'votes',
            ]),
            'sort_time_url' => $this->urlGenerator->generate('contao_qna_stage_questions', [
                'sessionId' => $session->id,
                'sort' => 'time',
            ]),
            'polling_interval' => $this->intervalFor($session->state),
            'error_translation_key' => $errorTranslationKey,
        ];
    }

    /**
     * @param iterable<QnaQuestionListItem> $questions
     *
     * @return array<int, string>
     */
    private function createVoteUrls(iterable $questions): array
    {
        $urls = [];

        foreach ($questions as $question) {
            $urls[$question->id] = $this->urlGenerator->generate('contao_qna_vote_create', [
                'sessionId' => $question->sessionId,
                'questionId' => $question->id,
            ]);
        }

        return $urls;
    }

    private function requirePublishedSession(int $sessionId): QnaSession
    {
        $session = $this->sessionGateway->findPublished($sessionId);

        if (!$session instanceof QnaSession) {
            throw new PageNotFoundException();
        }

        return $session;
    }

    private function intervalFor(SessionState $state): int
    {
        return SessionState::OPEN === $state
            ? $this->pollingInterval
            : $this->pollingInterval * self::IDLE_INTERVAL_MULTIPLIER;
    }

    private function normalizeSort(string $sort): string
    {
        return 'time' === $sort ? 'time' : 'votes';
    }

    private function createResponse(string $content, int $statusCode): Response
    {
        return new Response($content, $statusCode, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private function createStreamResponse(string $content): Response
    {
        return new Response($content, Response::HTTP_OK, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => self::TURBO_STREAM_CONTENT_TYPE.'; charset=UTF-8',
        ]);
    }
}
