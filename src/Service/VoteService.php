<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use HeimrichHannot\QnaBundle\Dto\QnaVoteState;
use HeimrichHannot\QnaBundle\Exception\QuestionAnsweredException;
use HeimrichHannot\QnaBundle\Exception\QuestionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
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
        // Discovery only: re-read ownership and answered state after acquiring the session lock.
        $discovered = $this->questionGateway->find($questionId)
            ?? throw new QuestionNotFoundException($questionId);
        $sessionId = $expectedSessionId ?? $discovered->sessionId;

        return $this->connection->transactional(function () use ($questionId, $sessionId): QnaVoteState {
            $session = $this->sessionGateway->find($sessionId, true);
            $question = $this->questionGateway->find($questionId, true)
                ?? throw new QuestionNotFoundException($questionId);

            if ($question->sessionId !== $sessionId) {
                throw new QuestionNotFoundException($questionId);
            }

            ($session ?? throw new SessionNotFoundException($sessionId))->assertOpen();
            if ($question->answered) {
                throw new QuestionAnsweredException();
            }

            $memberId = $this->memberProvider->getId();

            try {
                $this->voteGateway->create($question->id, $memberId, $this->clock->now()->getTimestamp());
            } catch (UniqueConstraintViolationException) {
                // A concurrent or repeated vote is successful from the member's perspective.
            }

            return $this->voteGateway->getState($question->id, $memberId, true);
        });
    }
}
