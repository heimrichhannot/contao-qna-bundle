<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Exception\QuestionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotFoundException;
use HeimrichHannot\QnaBundle\Exception\SessionNotOpenException;
use HeimrichHannot\QnaBundle\Exception\SessionNotPublishedException;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;

final readonly class QuestionAnswerService
{
    public function __construct(
        private QnaSessionGateway $sessionGateway,
        private QnaQuestionGateway $questionGateway,
        private Connection $connection,
    ) {
    }

    public function setAnswered(int $sessionId, int $questionId, bool $answered): void
    {
        $this->connection->transactional(function () use ($sessionId, $questionId, $answered): void {
            $question = $this->questionGateway->find($questionId, true)
                ?? throw new QuestionNotFoundException($questionId);

            if ($question->sessionId !== $sessionId) {
                throw new QuestionNotFoundException($questionId);
            }

            $session = $this->sessionGateway->find($sessionId)
                ?? throw new SessionNotFoundException($sessionId);

            if (!$session->published) {
                throw new SessionNotPublishedException($sessionId);
            }

            if (SessionState::OPEN !== $session->state) {
                throw new SessionNotOpenException($sessionId, $session->state);
            }

            if ($question->answered !== $answered) {
                $this->questionGateway->setAnswered($questionId, $answered);
            }
        });
    }
}
