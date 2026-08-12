<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Dto;

use HeimrichHannot\QnaBundle\Enum\SessionState;

final readonly class QnaSession
{
    public function __construct(
        public int $id,
        public string $title,
        public string $alias,
        public bool $published,
        public SessionState $state,
        public ?int $startedAt,
        public ?int $endedAt,
    ) {
    }

    public function withState(SessionState $state, int $timestamp): self
    {
        return new self(
            $this->id,
            $this->title,
            $this->alias,
            $this->published,
            $state,
            SessionState::OPEN === $state ? $timestamp : $this->startedAt,
            SessionState::CLOSED === $state ? $timestamp : $this->endedAt,
        );
    }
}
