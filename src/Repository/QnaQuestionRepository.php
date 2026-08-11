<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class QnaQuestionRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function deleteByMemberId(int $memberId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_qna_question WHERE memberId = :memberId',
            ['memberId' => $memberId],
            ['memberId' => ParameterType::INTEGER],
        );
    }
}
