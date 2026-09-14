<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View\Model;

use HeimrichHannot\QnaBundle\Model\QuestionListItem;

final readonly class ReaderContext
{
    /**
     * @param list<QuestionListItem> $questions
     * @param array<int, string>     $voteUrls
     */
    public function __construct(
        public ReaderView $view,
        public array $questions,
        public string $questionFormAction,
        public array $voteUrls,
        public ?string $requestToken,
        public int $maxQuestionLength,
        public int $pollingInterval,
        public ?string $errorTranslationKey,
        public string $questionValue,
    ) {
    }

    /**
     * @return array{
     *     view: ReaderView,
     *     questions: list<QuestionListItem>,
     *     question_form_action: string,
     *     vote_urls: array<int, string>,
     *     request_token: string|null,
     *     max_question_length: int,
     *     polling_interval: int,
     *     error_translation_key: string|null,
     *     question_value: string
     * }
     */
    public function templateContext(): array
    {
        return [
            'view' => $this->view,
            'questions' => $this->questions,
            'question_form_action' => $this->questionFormAction,
            'vote_urls' => $this->voteUrls,
            'request_token' => $this->requestToken,
            'max_question_length' => $this->maxQuestionLength,
            'polling_interval' => $this->pollingInterval,
            'error_translation_key' => $this->errorTranslationKey,
            'question_value' => $this->questionValue,
        ];
    }
}
