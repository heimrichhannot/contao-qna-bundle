<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use HeimrichHannot\QnaBundle\Dto\QnaVoteState;
use HeimrichHannot\QnaBundle\Exception\QuestionAnsweredException;
use HeimrichHannot\QnaBundle\Gateway\LockedContextLoader;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;
use Psr\Clock\ClockInterface;

final readonly class VoteService
{
    public function __construct(
        private LockedContextLoader $contextLoader,
        private QnaVoteGateway $voteGateway,
        private FrontendMemberProvider $memberProvider,
        private ClockInterface $clock,
        private Connection $connection,
    ) {
    }

    public function vote(int $sessionId, int $questionId): QnaVoteState
    {
        return $this->connection->transactional(function () use ($questionId, $sessionId): QnaVoteState {
            $question = $this->contextLoader->lockOpenSessionWithQuestion($sessionId, $questionId)->question;

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
