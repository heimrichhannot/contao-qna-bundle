<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Gateway\LockedContextLoader;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;

final readonly class QuestionAnswerService
{
    public function __construct(
        private LockedContextLoader $contextLoader,
        private QnaQuestionGateway $questionGateway,
        private Connection $connection,
    ) {
    }

    public function setAnswered(int $sessionId, int $questionId, bool $answered): void
    {
        $this->connection->transactional(function () use ($sessionId, $questionId, $answered): void {
            $question = $this->contextLoader->lockOpenSessionWithQuestion($sessionId, $questionId)->question;

            if ($question->answered !== $answered) {
                $this->questionGateway->setAnswered($questionId, $answered);
            }
        });
    }
}
