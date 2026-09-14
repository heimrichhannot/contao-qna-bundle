<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use HeimrichHannot\QnaBundle\Configuration\QnaOptions;
use HeimrichHannot\QnaBundle\Enum\SessionState;

final readonly class PollingPolicy
{
    public function __construct(private QnaOptions $options)
    {
    }

    public function baseInterval(): int
    {
        return $this->options->pollingInterval;
    }

    public function intervalFor(SessionState $state): int
    {
        return SessionState::OPEN === $state
            ? $this->baseInterval()
            : $this->baseInterval() * $this->options->idlePollingIntervalMultiplier;
    }

    public function maxInterval(): int
    {
        return $this->baseInterval() * $this->options->maxPollingIntervalMultiplier;
    }
}
