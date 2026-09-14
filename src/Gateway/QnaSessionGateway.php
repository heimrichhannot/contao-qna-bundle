<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;

class QnaSessionGateway
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** For writes, acquire this lock first inside the owning service transaction. */
    public function find(int $sessionId, bool $forUpdate = false): ?QnaSession
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, title, alias, published, state, startedAt, endedAt
                FROM tl_qna_session
                WHERE id = :id
                SQL.($forUpdate ? ' FOR UPDATE' : ''),
            ['id' => $sessionId],
            ['id' => ParameterType::INTEGER],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findPublished(int $sessionId): ?QnaSession
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, title, alias, published, state, startedAt, endedAt
                FROM tl_qna_session
                WHERE id = :id AND published = :published
                SQL,
            ['id' => $sessionId, 'published' => '1'],
            ['id' => ParameterType::INTEGER, 'published' => ParameterType::STRING],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findPublishedByAlias(string $alias): ?QnaSession
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, title, alias, published, state, startedAt, endedAt
                FROM tl_qna_session
                WHERE alias = :alias AND published = :published
                SQL,
            ['alias' => $alias, 'published' => '1'],
            ['alias' => ParameterType::STRING, 'published' => ParameterType::STRING],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * @return list<QnaSession>
     */
    public function findAllPublished(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT id, title, alias, published, state, startedAt, endedAt
                FROM tl_qna_session
                WHERE published = :published
                ORDER BY title ASC
                SQL,
            ['published' => '1'],
            ['published' => ParameterType::STRING],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function markOpen(int $sessionId, int $timestamp): bool
    {
        return 1 === $this->connection->executeStatement(
            <<<'SQL'
                UPDATE tl_qna_session
                SET state = :newState, startedAt = :timestamp, tstamp = :timestamp
                WHERE id = :id AND state = :expectedState
                SQL,
            [
                'newState' => SessionState::OPEN->value,
                'timestamp' => $timestamp,
                'id' => $sessionId,
                'expectedState' => SessionState::WAITING->value,
            ],
            [
                'newState' => ParameterType::STRING,
                'timestamp' => ParameterType::INTEGER,
                'id' => ParameterType::INTEGER,
                'expectedState' => ParameterType::STRING,
            ],
        );
    }

    public function markClosed(int $sessionId, int $timestamp): bool
    {
        return 1 === $this->connection->executeStatement(
            <<<'SQL'
                UPDATE tl_qna_session
                SET state = :newState, endedAt = :timestamp, tstamp = :timestamp
                WHERE id = :id AND state = :expectedState
                SQL,
            [
                'newState' => SessionState::CLOSED->value,
                'timestamp' => $timestamp,
                'id' => $sessionId,
                'expectedState' => SessionState::OPEN->value,
            ],
            [
                'newState' => ParameterType::STRING,
                'timestamp' => ParameterType::INTEGER,
                'id' => ParameterType::INTEGER,
                'expectedState' => ParameterType::STRING,
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): QnaSession
    {
        $row = new Row($row);

        return new QnaSession(
            $row->int('id'),
            $row->string('title'),
            $row->string('alias'),
            $row->bool('published'),
            SessionState::from($row->string('state')),
            $row->nullableInt('startedAt'),
            $row->nullableInt('endedAt'),
        );
    }
}
