<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\QnaBundle\Dto\QnaVoteState;

class QnaVoteGateway
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(int $questionId, int $memberId, int $createdAt): void
    {
        $this->connection->insert(
            'tl_qna_vote',
            [
                'pid' => $questionId,
                'memberId' => $memberId,
                'createdAt' => $createdAt,
                'tstamp' => $createdAt,
            ],
            [
                'pid' => ParameterType::INTEGER,
                'memberId' => ParameterType::INTEGER,
                'createdAt' => ParameterType::INTEGER,
                'tstamp' => ParameterType::INTEGER,
            ],
        );
    }

    public function getState(int $questionId, int $memberId): QnaVoteState
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    COUNT(*) AS voteCount,
                    COALESCE(MAX(CASE WHEN memberId = :memberId THEN 1 ELSE 0 END), 0) AS hasVoted
                FROM tl_qna_vote
                WHERE pid = :questionId
                SQL,
            ['questionId' => $questionId, 'memberId' => $memberId],
            ['questionId' => ParameterType::INTEGER, 'memberId' => ParameterType::INTEGER],
        );

        if (false === $row) {
            throw new \UnexpectedValueException('The vote-state query did not return a result.');
        }

        $voteCount = $row['voteCount'] ?? null;
        $hasVoted = $row['hasVoted'] ?? null;

        if (!\is_int($voteCount) && !\is_string($voteCount)) {
            throw new \UnexpectedValueException('Column "voteCount" is not an integer value.');
        }

        if (!\is_bool($hasVoted) && !\is_int($hasVoted) && !\is_string($hasVoted)) {
            throw new \UnexpectedValueException('Column "hasVoted" is not a boolean value.');
        }

        return new QnaVoteState($questionId, (int) $voteCount, (bool) $hasVoted);
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
