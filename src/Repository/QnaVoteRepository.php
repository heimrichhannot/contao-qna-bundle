<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class QnaVoteRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function deleteByMemberIdOrQuestionAuthor(int $memberId): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                DELETE FROM tl_qna_vote
                WHERE memberId = :memberId
                   OR pid IN (SELECT id FROM tl_qna_question WHERE memberId = :memberId)
                SQL,
            ['memberId' => $memberId],
            ['memberId' => ParameterType::INTEGER],
        );
    }
}
