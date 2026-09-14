<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Configuration\QnaOptions;
use HeimrichHannot\QnaBundle\Domain\Question;
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
        private QnaOptions $options,
        private QnaVoteGateway $voteGateway,
        private Connection $connection,
    ) {
    }

    public function create(int $sessionId, string $question): Question
    {
        return $this->connection->transactional(function () use ($sessionId, $question): Question {
            $session = $this->sessionGateway->find($sessionId, true)
                ?? throw new SessionNotFoundException($sessionId);
            $session->assertOpen();
            $memberId = $this->memberProvider->getId();
            $question = trim($question);

            if ('' === $question) {
                throw new EmptyQuestionException();
            }

            if (mb_strlen($question) > $this->options->maxQuestionLength) {
                throw new QuestionTooLongException($this->options->maxQuestionLength);
            }

            $timestamp = $this->clock->now()->getTimestamp();
            $latestCreatedAt = $this->questionGateway->findLatestCreatedAt($session->id, $memberId, true);

            if (
                null !== $latestCreatedAt
                && $timestamp - $latestCreatedAt < $this->options->questionCooldown
            ) {
                throw new QuestionCooldownException($this->options->questionCooldown - ($timestamp - $latestCreatedAt));
            }

            $questionId = $this->questionGateway->create($session->id, $memberId, $question, $timestamp);
            $this->voteGateway->create($questionId, $memberId, $timestamp);

            return new Question($questionId, $session->id, $memberId, $question, $timestamp);
        });
    }
}
