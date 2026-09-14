<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\QnaBundle\Domain\Question;
use HeimrichHannot\QnaBundle\Domain\QuestionListItem;
use HeimrichHannot\QnaBundle\Enum\QuestionSort;

class QnaQuestionGateway
{
    private const string LIST_SQL = <<<'SQL'
        SELECT
            q.id,
            q.pid,
            q.memberId,
            q.question,
            q.createdAt,
            q.answered,
            COUNT(v.id) AS voteCount,
            MAX(CASE WHEN :memberId > 0 AND v.memberId = :memberId THEN 1 ELSE 0 END) AS hasVoted
        FROM tl_qna_question q
        LEFT JOIN tl_qna_vote v ON v.pid = q.id
        WHERE q.pid = :sessionId
        GROUP BY q.id, q.pid, q.memberId, q.question, q.createdAt, q.answered
        ORDER BY %s
        SQL;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function find(int $questionId, bool $forUpdate = false): ?Question
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, pid, memberId, question, createdAt, answered
                FROM tl_qna_question
                WHERE id = :id
                SQL.($forUpdate ? ' FOR UPDATE' : ''),
            ['id' => $questionId],
            ['id' => ParameterType::INTEGER],
        );

        if (false === $row) {
            return null;
        }

        $row = new Row($row);

        return new Question(
            $row->int('id'),
            $row->int('pid'),
            $row->int('memberId'),
            $row->string('question'),
            $row->int('createdAt'),
            $row->bool('answered'),
        );
    }

    public function setAnswered(int $questionId, bool $answered): void
    {
        $this->connection->update('tl_qna_question', ['answered' => $answered], ['id' => $questionId], [
            'answered' => ParameterType::BOOLEAN,
            'id' => ParameterType::INTEGER,
        ]);
    }

    /** Use a current read under the session lock for write preconditions, including empty history. */
    public function findLatestCreatedAt(int $sessionId, int $memberId, bool $forUpdate = false): ?int
    {
        $createdAt = $this->connection->fetchOne(
            <<<'SQL'
                SELECT createdAt
                FROM tl_qna_question
                WHERE pid = :sessionId AND memberId = :memberId
                ORDER BY createdAt DESC
                LIMIT 1
                SQL.($forUpdate ? ' FOR UPDATE' : ''),
            ['sessionId' => $sessionId, 'memberId' => $memberId],
            ['sessionId' => ParameterType::INTEGER, 'memberId' => ParameterType::INTEGER],
        );

        return false === $createdAt || null === $createdAt
            ? null
            : (new Row(['createdAt' => $createdAt]))->int('createdAt');
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
     * @return list<QuestionListItem>
     */
    public function findForSession(
        int $sessionId,
        int $memberId,
        QuestionSort $sort = QuestionSort::VOTES,
    ): array {
        return $this->findList($sessionId, $memberId, $sort);
    }

    /**
     * Loads the stage question list without member-specific vote state.
     *
     * @return list<QuestionListItem>
     */
    public function findForStage(int $sessionId, QuestionSort $sort = QuestionSort::VOTES): array
    {
        return $this->findList($sessionId, null, $sort);
    }

    public function deleteByMemberId(int $memberId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_qna_question WHERE memberId = :memberId',
            ['memberId' => $memberId],
            ['memberId' => ParameterType::INTEGER],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateListItem(array $row): QuestionListItem
    {
        $row = new Row($row);

        return new QuestionListItem(
            $row->int('id'),
            $row->int('pid'),
            $row->int('memberId'),
            $row->string('question'),
            $row->int('createdAt'),
            $row->int('voteCount'),
            $row->bool('hasVoted'),
            $row->bool('answered'),
        );
    }

    /** @return list<QuestionListItem> */
    private function findList(int $sessionId, ?int $memberId, QuestionSort $sort): array
    {
        // The interpolated ORDER BY fragment is defined by QuestionSort; no request value reaches the SQL template.
        $sql = \sprintf(self::LIST_SQL, $sort->orderBySql());
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            ['sessionId' => $sessionId, 'memberId' => $memberId ?? 0],
            ['sessionId' => ParameterType::INTEGER, 'memberId' => ParameterType::INTEGER],
        );

        return array_map($this->hydrateListItem(...), $rows);
    }
}
