<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\QnaBundle\Dto\QnaQuestion;
use HeimrichHannot\QnaBundle\Dto\QnaQuestionListItem;

class QnaQuestionGateway
{
    private const string LIST_BY_VOTES_SQL = <<<'SQL'
        SELECT
            q.id,
            q.pid,
            q.memberId,
            q.question,
            q.createdAt,
            COUNT(v.id) AS voteCount,
            MAX(CASE WHEN v.memberId = :memberId THEN 1 ELSE 0 END) AS hasVoted
        FROM tl_qna_question q
        LEFT JOIN tl_qna_vote v ON v.pid = q.id
        WHERE q.pid = :sessionId
        GROUP BY q.id, q.pid, q.memberId, q.question, q.createdAt
        ORDER BY voteCount DESC, q.createdAt ASC
        SQL;

    private const string LIST_BY_TIME_SQL = <<<'SQL'
        SELECT
            q.id,
            q.pid,
            q.memberId,
            q.question,
            q.createdAt,
            COUNT(v.id) AS voteCount,
            MAX(CASE WHEN v.memberId = :memberId THEN 1 ELSE 0 END) AS hasVoted
        FROM tl_qna_question q
        LEFT JOIN tl_qna_vote v ON v.pid = q.id
        WHERE q.pid = :sessionId
        GROUP BY q.id, q.pid, q.memberId, q.question, q.createdAt
        ORDER BY q.createdAt ASC
        SQL;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function find(int $questionId): ?QnaQuestion
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, pid, memberId, question, createdAt
                FROM tl_qna_question
                WHERE id = :id
                SQL,
            ['id' => $questionId],
            ['id' => ParameterType::INTEGER],
        );

        if (false === $row) {
            return null;
        }

        return new QnaQuestion(
            $this->intValue($row['id'] ?? null, 'id'),
            $this->intValue($row['pid'] ?? null, 'pid'),
            $this->intValue($row['memberId'] ?? null, 'memberId'),
            $this->stringValue($row['question'] ?? null, 'question'),
            $this->intValue($row['createdAt'] ?? null, 'createdAt'),
        );
    }

    public function findLatestCreatedAt(int $sessionId, int $memberId): ?int
    {
        $createdAt = $this->connection->fetchOne(
            <<<'SQL'
                SELECT MAX(createdAt)
                FROM tl_qna_question
                WHERE pid = :sessionId AND memberId = :memberId
                SQL,
            ['sessionId' => $sessionId, 'memberId' => $memberId],
            ['sessionId' => ParameterType::INTEGER, 'memberId' => ParameterType::INTEGER],
        );

        return false === $createdAt || null === $createdAt
            ? null
            : $this->intValue($createdAt, 'createdAt');
    }

    public function create(int $sessionId, int $memberId, string $question, int $createdAt): int
    {
        $this->connection->insert(
            'tl_qna_question',
            [
                'pid' => $sessionId,
                'memberId' => $memberId,
                'question' => $question,
                'createdAt' => $createdAt,
                'tstamp' => $createdAt,
            ],
            [
                'pid' => ParameterType::INTEGER,
                'memberId' => ParameterType::INTEGER,
                'question' => ParameterType::STRING,
                'createdAt' => ParameterType::INTEGER,
                'tstamp' => ParameterType::INTEGER,
            ],
        );

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Loads the complete question list in exactly one database query.
     *
     * @return list<QnaQuestionListItem>
     */
    public function findForSession(int $sessionId, int $memberId, string $sort = 'votes'): array
    {
        $sql = 'time' === $sort ? self::LIST_BY_TIME_SQL : self::LIST_BY_VOTES_SQL;
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            ['sessionId' => $sessionId, 'memberId' => $memberId],
            ['sessionId' => ParameterType::INTEGER, 'memberId' => ParameterType::INTEGER],
        );

        return array_map(
            fn (array $row): QnaQuestionListItem => new QnaQuestionListItem(
                $this->intValue($row['id'] ?? null, 'id'),
                $this->intValue($row['pid'] ?? null, 'pid'),
                $this->intValue($row['memberId'] ?? null, 'memberId'),
                $this->stringValue($row['question'] ?? null, 'question'),
                $this->intValue($row['createdAt'] ?? null, 'createdAt'),
                $this->intValue($row['voteCount'] ?? null, 'voteCount'),
                $this->boolValue($row['hasVoted'] ?? null, 'hasVoted'),
            ),
            $rows,
        );
    }

    public function deleteByMemberId(int $memberId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_qna_question WHERE memberId = :memberId',
            ['memberId' => $memberId],
            ['memberId' => ParameterType::INTEGER],
        );
    }

    private function intValue(mixed $value, string $column): int
    {
        if (!\is_int($value) && !\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" is not an integer value.', $column));
        }

        return (int) $value;
    }

    private function stringValue(mixed $value, string $column): string
    {
        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" is not a string value.', $column));
        }

        return $value;
    }

    private function boolValue(mixed $value, string $column): bool
    {
        if (!\is_bool($value) && !\is_int($value) && !\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" is not a boolean value.', $column));
        }

        return (bool) $value;
    }
}
