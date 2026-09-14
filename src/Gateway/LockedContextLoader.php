<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use HeimrichHannot\QnaBundle\Exception\QuestionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;

/**
 * Centralizes the locking and error-precedence rules for question write paths.
 * The session is locked before the question to give all such paths the same lock
 * order and prevent deadlocks. Question existence and ownership are validated
 * before session existence so a missing or unrelated question takes precedence.
 */
final readonly class LockedContextLoader
{
    public function __construct(
        private QnaSessionGateway $sessionGateway,
        private QnaQuestionGateway $questionGateway,
    ) {
    }

    /**
     * Must be called inside a transaction owned by the caller; this method does not start one.
     */
    public function lockOpenSessionWithQuestion(int $sessionId, int $questionId): LockedQuestionContext
    {
        $session = $this->sessionGateway->find($sessionId, true);
        $question = $this->questionGateway->find($questionId, true)
            ?? throw new QuestionNotFoundException($questionId);

        if ($question->sessionId !== $sessionId) {
            throw new QuestionNotFoundException($questionId);
        }

        ($session ?? throw new SessionNotFoundException($sessionId))->assertOpen();

        return new LockedQuestionContext($session, $question);
    }
}
