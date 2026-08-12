<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Dto;

final readonly class QnaVoteState
{
    public function __construct(
        public int $questionId,
        public int $voteCount,
        public bool $hasVoted,
    ) {
    }
}
