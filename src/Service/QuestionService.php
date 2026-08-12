<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use HeimrichHannot\QnaBundle\Dto\QnaQuestion;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\EmptyQuestionException;
use HeimrichHannot\QnaBundle\Exception\QuestionCooldownException;
use HeimrichHannot\QnaBundle\Exception\QuestionTooLongException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use Psr\Clock\ClockInterface;

final readonly class QuestionService
{
    public function __construct(
        private QnaSessionGateway $sessionGateway,
        private QnaQuestionGateway $questionGateway,
        private FrontendMemberProvider $memberProvider,
        private ClockInterface $clock,
        private int $maxQuestionLength,
        private int $questionCooldown,
    ) {
    }

    public function create(int $sessionId, string $question): QnaQuestion
    {
        $session = $this->requireOpenSession($sessionId);
        $memberId = $this->memberProvider->getId();
        $question = trim($question);

        if ('' === $question) {
            throw new EmptyQuestionException();
        }

        if (mb_strlen($question) > $this->maxQuestionLength) {
            throw new QuestionTooLongException($this->maxQuestionLength);
        }

        $timestamp = $this->clock->now()->getTimestamp();
        $latestCreatedAt = $this->questionGateway->findLatestCreatedAt($session->id, $memberId);

        if (
            null !== $latestCreatedAt
            && $timestamp - $latestCreatedAt < $this->questionCooldown
        ) {
            throw new QuestionCooldownException($this->questionCooldown - ($timestamp - $latestCreatedAt));
        }

        $questionId = $this->questionGateway->create($session->id, $memberId, $question, $timestamp);

        return new QnaQuestion($questionId, $session->id, $memberId, $question, $timestamp);
    }

    private function requireOpenSession(int $sessionId): QnaSession
    {
        $session = $this->sessionGateway->find($sessionId)
            ?? throw new SessionNotFoundException($sessionId);

        if (!$session->published) {
            throw new SessionNotPublishedException($sessionId);
        }

        if (SessionState::OPEN !== $session->state) {
            throw new SessionNotOpenException($sessionId, $session->state);
        }

        return $session;
    }
}
