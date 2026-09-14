<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Dto\QnaQuestion;
use HeimrichHannot\QnaBundle\Exception\EmptyQuestionException;
use HeimrichHannot\QnaBundle\Exception\QuestionCooldownException;
use HeimrichHannot\QnaBundle\Exception\QuestionTooLongException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;
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
        private QnaVoteGateway $voteGateway,
        private Connection $connection,
    ) {
    }

    public function create(int $sessionId, string $question): QnaQuestion
    {
        return $this->connection->transactional(function () use ($sessionId, $question): QnaQuestion {
            $session = $this->sessionGateway->find($sessionId, true)
                ?? throw new SessionNotFoundException($sessionId);
            $session->assertOpen();
            $memberId = $this->memberProvider->getId();
            $question = trim($question);

            if ('' === $question) {
                throw new EmptyQuestionException();
            }

            if (mb_strlen($question) > $this->maxQuestionLength) {
                throw new QuestionTooLongException($this->maxQuestionLength);
            }

            $timestamp = $this->clock->now()->getTimestamp();
            $latestCreatedAt = $this->questionGateway->findLatestCreatedAt($session->id, $memberId, true);

            if (
                null !== $latestCreatedAt
                && $timestamp - $latestCreatedAt < $this->questionCooldown
            ) {
                throw new QuestionCooldownException($this->questionCooldown - ($timestamp - $latestCreatedAt));
            }

            $questionId = $this->questionGateway->create($session->id, $memberId, $question, $timestamp);
            $this->voteGateway->create($questionId, $memberId, $timestamp);

            return new QnaQuestion($questionId, $session->id, $memberId, $question, $timestamp);
        });
    }
}
