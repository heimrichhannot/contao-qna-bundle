<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Dto;

final readonly class QnaQuestion
{
    public function __construct(
        public int $id,
        public int $sessionId,
        public int $memberId,
        public string $question,
        public int $createdAt,
    ) {
    }
}
