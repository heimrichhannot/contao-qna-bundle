<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use HeimrichHannot\QnaBundle\Configuration\QnaOptions;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Model\QuestionListItem;
use HeimrichHannot\QnaBundle\Model\Session;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\PollingPolicy;
use HeimrichHannot\QnaBundle\View\Model\ReaderContext;
use HeimrichHannot\QnaBundle\View\Model\ReaderInitialView;
use HeimrichHannot\QnaBundle\View\Model\ReaderView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class ReaderViewFactory
{
    public function __construct(
        private Environment $twig,
        private QnaQuestionGateway $questionGateway,
        private FrontendMemberProvider $memberProvider,
        private ContaoCsrfTokenManager $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private QnaOptions $options,
        private PollingPolicy $pollingPolicy,
    ) {
    }

    public function createInitial(Session $session): ReaderInitialView
    {
        return new ReaderInitialView(
            $session->id,
            $session->title,
            $this->frameId($session->id),
            $this->questionsFrameId($session->id),
        );
    }

    public function createDynamic(Session $session, bool $canInteract = true): ReaderView
    {
        return match ($session->state) {
            SessionState::WAITING => new ReaderView(
                $session->id,
                $this->frameId($session->id),
                $this->questionsFrameId($session->id),
                $this->controlsContentId($session->id),
                $session->state->value,
                'qna.reader.status.waiting',
                false,
                false,
                false,
            ),
            SessionState::OPEN => new ReaderView(
                $session->id,
                $this->frameId($session->id),
                $this->questionsFrameId($session->id),
                $this->controlsContentId($session->id),
                $session->state->value,
                'qna.reader.status.open',
                $canInteract,
                true,
                $canInteract,
            ),
            SessionState::CLOSED => new ReaderView(
                $session->id,
                $this->frameId($session->id),
                $this->questionsFrameId($session->id),
                $this->controlsContentId($session->id),
                $session->state->value,
                'qna.reader.status.closed',
                false,
                true,
                false,
            ),
        };
    }

    public function renderControls(
        Session $session,
        ?string $errorTranslationKey = null,
        string $questionValue = '',
    ): string {
        return $this->twig->render(
            '@Contao/qna/reader_controls_frame.html.twig',
            $this->createContext($session, false, $errorTranslationKey, $questionValue)->templateContext(),
        );
    }

    public function renderQuestions(Session $session, ?string $errorTranslationKey = null): string
    {
        return $this->twig->render(
            '@Contao/qna/reader_questions_frame.html.twig',
            $this->createContext($session, true, $errorTranslationKey)->templateContext(),
        );
    }

    public function renderUpdate(Session $session, bool $resetQuestionForm = false): string
    {
        $context = $this->createContext($session, true)->templateContext();
        $context['reset_question_form'] = $resetQuestionForm;

        return $this->twig->render('@Contao/qna/reader_update.stream.html.twig', $context);
    }

    private function createContext(
        Session $session,
        bool $includeQuestions,
        ?string $errorTranslationKey = null,
        string $questionValue = '',
    ): ReaderContext {
        $memberId = $this->memberProvider->getIdOrNull();
        $view = $this->createDynamic($session, null !== $memberId);
        $questions = $includeQuestions && $view->showQuestions
            ? $this->questionGateway->findForSession($session->id, $memberId ?? 0)
            : [];
        $requestToken = $view->showQuestionForm || ($includeQuestions && $view->showVoteButtons)
            ? $this->csrfTokenManager->getDefaultTokenValue()
            : null;

        return new ReaderContext(
            $view,
            $questions,
            $this->urlGenerator->generate('contao_qna_question_create', ['sessionId' => $session->id]),
            $this->createVoteUrls($questions),
            $requestToken,
            $this->options->maxQuestionLength,
            $this->pollingPolicy->intervalFor($session->state),
            $errorTranslationKey,
            $questionValue,
        );
    }

    /**
     * @param iterable<QuestionListItem> $questions
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

    private function frameId(int $sessionId): string
    {
        return \sprintf('qna-session-%d-reader', $sessionId);
    }

    private function questionsFrameId(int $sessionId): string
    {
        return \sprintf('qna-session-%d-questions', $sessionId);
    }

    private function controlsContentId(int $sessionId): string
    {
        return \sprintf('qna-session-%d-controls', $sessionId);
    }
}
