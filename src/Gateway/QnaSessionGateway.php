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

    public function find(int $sessionId): ?QnaSession
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, title, alias, published, state, startedAt, endedAt
                FROM tl_qna_session
                WHERE id = :id
                SQL,
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
        return new QnaSession(
            $this->intValue($row['id'] ?? null, 'id'),
            $this->stringValue($row['title'] ?? null, 'title'),
            $this->stringValue($row['alias'] ?? null, 'alias'),
            $this->boolValue($row['published'] ?? null, 'published'),
            SessionState::from($this->stringValue($row['state'] ?? null, 'state')),
            $this->nullableIntValue($row['startedAt'] ?? null, 'startedAt'),
            $this->nullableIntValue($row['endedAt'] ?? null, 'endedAt'),
        );
    }

    private function intValue(mixed $value, string $column): int
    {
        if (!\is_int($value) && !\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" is not an integer value.', $column));
        }

        return (int) $value;
    }

    private function nullableIntValue(mixed $value, string $column): ?int
    {
        return null === $value ? null : $this->intValue($value, $column);
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
