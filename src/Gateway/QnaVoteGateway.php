<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\QnaBundle\Domain\VoteState;

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
        // Both write services hold the session lock and wrap this call in their transaction.
        // A duplicate insert throws before the cached count can change.
        $this->connection->executeStatement(
            'UPDATE tl_qna_question SET voteCount = voteCount + 1 WHERE id = ?',
            [$questionId],
            [ParameterType::INTEGER],
        );
    }

    /** A locking read returns current state even inside an older repeatable-read snapshot. */
    public function getState(int $questionId, int $memberId, bool $forUpdate = false): VoteState
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    COUNT(*) AS voteCount,
                    COALESCE(MAX(CASE WHEN memberId = :memberId THEN 1 ELSE 0 END), 0) AS hasVoted
                FROM tl_qna_vote
                WHERE pid = :questionId
                SQL.($forUpdate ? ' FOR UPDATE' : ''),
            ['questionId' => $questionId, 'memberId' => $memberId],
            ['questionId' => ParameterType::INTEGER, 'memberId' => ParameterType::INTEGER],
        );

        if (false === $row) {
            throw new \UnexpectedValueException('The vote-state query did not return a result.');
        }

        $row = new Row($row);

        return new VoteState($questionId, $row->int('voteCount'), $row->bool('hasVoted'));
    }

    public function deleteByMemberIdOrQuestionAuthor(int $memberId): void
    {
        // MemberDataEraser holds all session locks; the unique vote key permits one decrement per question.
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE tl_qna_question q
                INNER JOIN tl_qna_vote v ON v.pid = q.id AND v.memberId = :memberId
                SET q.voteCount = CASE WHEN q.voteCount > 0 THEN q.voteCount - 1 ELSE 0 END
                SQL,
            ['memberId' => $memberId],
            ['memberId' => ParameterType::INTEGER],
        );
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
