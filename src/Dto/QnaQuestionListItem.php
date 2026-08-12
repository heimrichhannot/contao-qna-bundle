<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Dto;

final readonly class QnaQuestionListItem
{
    public function __construct(
        public int $id,
        public int $sessionId,
        public int $memberId,
        public string $question,
        public int $createdAt,
        public int $voteCount,
        public bool $hasVoted,
    ) {
    }
}
