<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View;

use HeimrichHannot\QnaBundle\Enum\QuestionSort;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Model\QuestionListItem;
use HeimrichHannot\QnaBundle\Model\Session;
use HeimrichHannot\QnaBundle\View\Model\StageUrlSet;
use HeimrichHannot\QnaBundle\View\Model\StageView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class StageViewFactory
{
    public function __construct(
        private Environment $twig,
        private QnaQuestionGateway $questionGateway,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(
        Session $session,
        QuestionSort $sort,
        bool $canControl,
        ?string $errorTranslationKey = null,
    ): StageView {
        $showQuestions = SessionState::WAITING !== $session->state;
        $showStartButton = $canControl && SessionState::WAITING === $session->state;
        $showStopButton = $canControl && SessionState::OPEN === $session->state;
        $routeParameters = ['sessionId' => $session->id, 'sort' => $sort->value];
        $questions = $showQuestions ? $this->questionGateway->findForStage($session->id, $sort) : [];
        $answerUrls = [];

        if ($showStopButton) {
            foreach ($questions as $question) {
                $answerUrls[$question->id] = $this->urlGenerator->generate(
                    $question->answered ? 'contao_qna_question_unanswered' : 'contao_qna_question_answered',
                    $routeParameters + ['questionId' => $question->id],
                );
            }
        }

        return new StageView(
            $session,
            'qna.stage.status.'.$session->state->value,
            $questions,
            array_values(array_filter($questions, static fn (QuestionListItem $question): bool => !$question->answered)),
            array_values(array_filter($questions, static fn (QuestionListItem $question): bool => $question->answered)),
            new StageUrlSet(
                $this->urlGenerator->generate('contao_qna_session_start', $routeParameters),
                $this->urlGenerator->generate('contao_qna_session_stop', $routeParameters),
                $this->urlGenerator->generate('contao_qna_stage_questions', [
                    'sessionId' => $session->id,
                    'sort' => QuestionSort::VOTES->value,
                ]),
                $this->urlGenerator->generate('contao_qna_stage_questions', [
                    'sessionId' => $session->id,
                    'sort' => QuestionSort::TIME->value,
                ]),
                $answerUrls,
            ),
            $showQuestions,
            $showStartButton,
            $showStopButton,
            \sprintf('qna-session-%d-stage', $session->id),
            $sort->value,
            $errorTranslationKey,
        );
    }

    public function renderFrame(StageView $view, ?string $requestToken, int $pollingInterval): string
    {
        return $this->twig->render(
            '@Contao/qna/stage_questions.html.twig',
            $view->templateContext($requestToken, $pollingInterval),
        );
    }

    public function renderUpdate(StageView $view, ?string $requestToken, int $pollingInterval): string
    {
        return $this->twig->render(
            '@Contao/qna/stage_update.stream.html.twig',
            $view->templateContext($requestToken, $pollingInterval),
        );
    }
}
