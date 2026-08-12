<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Dto;

final readonly class QnaReaderView
{
    public function __construct(
        public int $sessionId,
        public string $frameId,
        public string $questionsFrameId,
        public string $state,
        public string $statusTranslationKey,
        public bool $showQuestionForm,
        public bool $showQuestions,
        public bool $showVoteButtons,
    ) {
    }
}
