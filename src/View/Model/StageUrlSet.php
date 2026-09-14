<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View\Model;

final readonly class StageUrlSet
{
    /** @param array<int, string> $answers */
    public function __construct(
        public string $start,
        public string $stop,
        public string $sortVotes,
        public string $sortTime,
        public array $answers,
    ) {
    }
}
