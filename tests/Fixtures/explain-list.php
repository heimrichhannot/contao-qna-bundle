<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if ('1' !== getenv('QNA_DATABASE_TESTS')) {
    throw new RuntimeException('Set QNA_DATABASE_TESTS=1 to create temporary benchmark data.');
}
$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => getenv('QNA_TEST_DB_HOST') ?: 'db',
    'dbname' => getenv('QNA_TEST_DB_NAME') ?: 'qna_phase5_test',
    'user' => getenv('QNA_TEST_DB_USER') ?: 'db',
    'password' => getenv('QNA_TEST_DB_PASSWORD') ?: 'db',
]);
$connection->beginTransaction();
try {
    $connection->insert('tl_qna_session', ['title' => 'EXPLAIN fixture', 'alias' => uniqid('explain-', true)]);
    $sessionId = (int) $connection->lastInsertId();
    $questions = new QnaQuestionGateway($connection);
    for ($i = 0; $i < 50; ++$i) {
        $id = $questions->create($sessionId, 1, 'Benchmark question '.$i, 100 + $i);
        $values = [];
        for ($member = 1; $member <= 200; ++$member) {
            $values[] = '('.$id.','.$member.')';
        }
        $connection->executeStatement('INSERT INTO tl_qna_vote (pid, memberId) VALUES '.implode(',', $values));
    }
    if (isset($connection->createSchemaManager()->listTableColumns('tl_qna_question')['votecount'])) {
        $connection->executeStatement('UPDATE tl_qna_question SET voteCount = 200 WHERE pid = ?', [$sessionId]);
    }
    $sql = (new ReflectionClass(QnaQuestionGateway::class))->getConstant('LIST_SQL');
    if (!is_string($sql)) {
        throw new LogicException('Missing list query.');
    }
    $sql = sprintf($sql, 'voteCount DESC, q.createdAt ASC');
    echo "50 questions, 200 votes each (10,000 votes), member 42\n";
    echo $sql."\n";
    echo json_encode($connection->fetchAllAssociative('EXPLAIN '.$sql, ['sessionId' => $sessionId, 'memberId' => 42]), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n";
    $analysis = $connection->fetchOne('ANALYZE FORMAT=JSON '.$sql, ['sessionId' => $sessionId, 'memberId' => 42]);
    if (!is_string($analysis)) {
        throw new LogicException('Missing query analysis.');
    }
    echo $analysis."\n";
} finally {
    $connection->rollBack();
}
