<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

final class RebuildVoteCountMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        $schema = $this->connection->createSchemaManager();
        if (!$schema->tablesExist(['tl_qna_session', 'tl_qna_question', 'tl_qna_vote'])) {
            return false;
        }
        if (!isset($schema->listTableColumns('tl_qna_question')['votecount'])) {
            return true;
        }

        return false !== $this->connection->fetchOne(
            'SELECT q.id FROM tl_qna_question q WHERE q.voteCount <> (SELECT COUNT(*) FROM tl_qna_vote v WHERE v.pid = q.id) LIMIT 1',
        );
    }

    public function run(): MigrationResult
    {
        $schema = $this->connection->createSchemaManager();
        if (!isset($schema->listTableColumns('tl_qna_question')['votecount'])) {
            // Contao runs bundle migrations before its schema update. DDL must precede the transaction.
            $this->connection->executeStatement('ALTER TABLE tl_qna_question ADD voteCount INT UNSIGNED NOT NULL DEFAULT 0');
        }
        $this->connection->transactional(static function (Connection $connection): void {
            $connection->fetchFirstColumn('SELECT id FROM tl_qna_session ORDER BY id FOR UPDATE');
            $connection->executeStatement('UPDATE tl_qna_question q SET voteCount = (SELECT COUNT(*) FROM tl_qna_vote v WHERE v.pid = q.id)');
        });

        return $this->createResult(true);
    }
}
