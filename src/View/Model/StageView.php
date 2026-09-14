<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View\Model;

use HeimrichHannot\QnaBundle\Model\QuestionListItem;
use HeimrichHannot\QnaBundle\Model\Session;

final readonly class StageView
{
    /**
     * @param list<QuestionListItem> $questions
     * @param list<QuestionListItem> $unansweredQuestions
     * @param list<QuestionListItem> $answeredQuestions
     */
    public function __construct(
        public Session $session,
        public string $statusTranslationKey,
        public array $questions,
        public array $unansweredQuestions,
        public array $answeredQuestions,
        public StageUrlSet $urls,
        public bool $showQuestions,
        public bool $showStartButton,
        public bool $showStopButton,
        public string $frameId,
        public string $sort,
        public ?string $errorTranslationKey,
    ) {
    }

    /**
     * @return array{
     *     session: Session,
     *     status_translation_key: string,
     *     questions: list<QuestionListItem>,
     *     unanswered_questions: list<QuestionListItem>,
     *     answered_questions: list<QuestionListItem>,
     *     answer_urls: array<int, string>,
     *     show_questions: bool,
     *     show_start_button: bool,
     *     show_stop_button: bool,
     *     start_url: string,
     *     stop_url: string,
     *     request_token: string|null,
     *     frame_id: string,
     *     sort: string,
     *     sort_votes_url: string,
     *     sort_time_url: string,
     *     polling_interval: int,
     *     error_translation_key: string|null
     * }
     */
    public function templateContext(?string $requestToken, int $pollingInterval): array
    {
        return [
            'session' => $this->session,
            'status_translation_key' => $this->statusTranslationKey,
            'questions' => $this->questions,
            'unanswered_questions' => $this->unansweredQuestions,
            'answered_questions' => $this->answeredQuestions,
            'answer_urls' => $this->urls->answers,
            'show_questions' => $this->showQuestions,
            'show_start_button' => $this->showStartButton,
            'show_stop_button' => $this->showStopButton,
            'start_url' => $this->urls->start,
            'stop_url' => $this->urls->stop,
            'request_token' => $requestToken,
            'frame_id' => $this->frameId,
            'sort' => $this->sort,
            'sort_votes_url' => $this->urls->sortVotes,
            'sort_time_url' => $this->urls->sortTime,
            'polling_interval' => $pollingInterval,
            'error_translation_key' => $this->errorTranslationKey,
        ];
    }
}
