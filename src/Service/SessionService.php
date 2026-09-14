<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\InvalidSessionTransitionException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Model\Session;
use Psr\Clock\ClockInterface;

final readonly class SessionService
{
    public function __construct(
        private QnaSessionGateway $sessionGateway,
        private ClockInterface $clock,
        private Connection $connection,
    ) {
    }

    public function start(int $sessionId): Session
    {
        return $this->connection->transactional(function () use ($sessionId): Session {
            $session = $this->lockPublishedSession($sessionId);

            if (SessionState::WAITING !== $session->state) {
                throw new InvalidSessionTransitionException($session->state, SessionState::OPEN);
            }

            $timestamp = $this->clock->now()->getTimestamp();

            if (!$this->sessionGateway->markOpen($sessionId, $timestamp)) {
                $current = $this->sessionGateway->find($sessionId, true);

                throw new InvalidSessionTransitionException($current->state ?? $session->state, SessionState::OPEN);
            }

            return $session->withState(SessionState::OPEN, $timestamp);
        });
    }

    public function stop(int $sessionId): Session
    {
        return $this->connection->transactional(function () use ($sessionId): Session {
            $session = $this->lockPublishedSession($sessionId);

            if (SessionState::OPEN !== $session->state) {
                throw new InvalidSessionTransitionException($session->state, SessionState::CLOSED);
            }

            $timestamp = $this->clock->now()->getTimestamp();

            if (!$this->sessionGateway->markClosed($sessionId, $timestamp)) {
                $current = $this->sessionGateway->find($sessionId, true);

                throw new InvalidSessionTransitionException($current->state ?? $session->state, SessionState::CLOSED);
            }

            return $session->withState(SessionState::CLOSED, $timestamp);
        });
    }

    private function lockPublishedSession(int $sessionId): Session
    {
        $session = $this->sessionGateway->find($sessionId, true)
            ?? throw new SessionNotFoundException($sessionId);

        $session->assertPublished();

        return $session;
    }
}
