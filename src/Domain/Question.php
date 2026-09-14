<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Domain;

final readonly class Question
{
    public function __construct(
        public int $id,
        public int $sessionId,
        public int $memberId,
        public string $question,
        public int $createdAt,
        public bool $answered = false,
    ) {
    }
}
