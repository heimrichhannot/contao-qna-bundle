<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Configuration;

final readonly class QnaOptions
{
    public function __construct(
        public int $pollingInterval,
        public int $maxQuestionLength,
        public int $questionCooldown,
        public int $idlePollingIntervalMultiplier,
        public int $maxPollingIntervalMultiplier,
    ) {
    }
}
