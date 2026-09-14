<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Model;

use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;

final readonly class Session
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

    public function assertPublished(): void
    {
        if (!$this->published) {
            throw new SessionNotPublishedException($this->id);
        }
    }

    public function assertOpen(): void
    {
        $this->assertPublished();

        if (SessionState::OPEN !== $this->state) {
            throw new SessionNotOpenException($this->id, $this->state);
        }
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
