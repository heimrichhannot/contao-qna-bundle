<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Dto\QnaVoteState;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\QuestionAnsweredException;
use HeimrichHannot\QnaBundle\Exception\QuestionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;
use Psr\Clock\ClockInterface;

final readonly class VoteService
{
    public function __construct(
        private QnaSessionGateway $sessionGateway,
        private QnaQuestionGateway $questionGateway,
        private QnaVoteGateway $voteGateway,
        private FrontendMemberProvider $memberProvider,
        private ClockInterface $clock,
        private Connection $connection,
    ) {
    }

    public function vote(int $questionId, ?int $expectedSessionId = null): QnaVoteState
    {
        return $this->connection->transactional(function () use ($questionId, $expectedSessionId): QnaVoteState {
            $question = $this->questionGateway->find($questionId, true)
                ?? throw new QuestionNotFoundException($questionId);

            if (null !== $expectedSessionId && $question->sessionId !== $expectedSessionId) {
                throw new QuestionNotFoundException($questionId);
            }

            $this->requireOpenSession($question->sessionId);
            if ($question->answered) {
                throw new QuestionAnsweredException();
            }

            $memberId = $this->memberProvider->getId();

            try {
                $this->voteGateway->create($question->id, $memberId, $this->clock->now()->getTimestamp());
            } catch (UniqueConstraintViolationException) {
                // A concurrent or repeated vote is successful from the member's perspective.
            }

            return $this->voteGateway->getState($question->id, $memberId);
        });
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
