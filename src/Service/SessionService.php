<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\InvalidSessionTransitionException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use Psr\Clock\ClockInterface;

final readonly class SessionService
{
    public function __construct(
        private QnaSessionGateway $sessionGateway,
        private ClockInterface $clock,
    ) {
    }

    public function start(int $sessionId): QnaSession
    {
        $session = $this->requirePublishedSession($sessionId);

        if (SessionState::WAITING !== $session->state) {
            throw new InvalidSessionTransitionException($session->state, SessionState::OPEN);
        }

        $timestamp = $this->clock->now()->getTimestamp();

        if (!$this->sessionGateway->markOpen($sessionId, $timestamp)) {
            $current = $this->sessionGateway->find($sessionId);

            throw new InvalidSessionTransitionException($current->state ?? $session->state, SessionState::OPEN);
        }

        return $session->withState(SessionState::OPEN, $timestamp);
    }

    public function stop(int $sessionId): QnaSession
    {
        $session = $this->requirePublishedSession($sessionId);

        if (SessionState::OPEN !== $session->state) {
            throw new InvalidSessionTransitionException($session->state, SessionState::CLOSED);
        }

        $timestamp = $this->clock->now()->getTimestamp();

        if (!$this->sessionGateway->markClosed($sessionId, $timestamp)) {
            $current = $this->sessionGateway->find($sessionId);

            throw new InvalidSessionTransitionException($current->state ?? $session->state, SessionState::CLOSED);
        }

        return $session->withState(SessionState::CLOSED, $timestamp);
    }

    private function requirePublishedSession(int $sessionId): QnaSession
    {
        $session = $this->sessionGateway->find($sessionId)
            ?? throw new SessionNotFoundException($sessionId);

        if (!$session->published) {
            throw new SessionNotPublishedException($sessionId);
        }

        return $session;
    }
}
