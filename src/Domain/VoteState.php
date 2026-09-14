<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Domain;

final readonly class VoteState
{
    public function __construct(
        public int $questionId,
        public int $voteCount,
        public bool $hasVoted,
    ) {
    }
}
