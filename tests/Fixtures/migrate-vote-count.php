<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use HeimrichHannot\QnaBundle\Migration\RebuildVoteCountMigration;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if ('1' !== getenv('QNA_DATABASE_TESTS')) {
    throw new RuntimeException('Set QNA_DATABASE_TESTS=1 to migrate a disposable database.');
}
$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => getenv('QNA_TEST_DB_HOST') ?: 'db',
    'dbname' => getenv('QNA_TEST_DB_NAME') ?: 'db',
    'user' => getenv('QNA_TEST_DB_USER') ?: 'db',
    'password' => getenv('QNA_TEST_DB_PASSWORD') ?: 'db',
]);
$migration = new RebuildVoteCountMigration($connection);
echo 'pending='.(int) $migration->shouldRun()."\n";
echo $migration->run()->getMessage()."\n";
echo 'pending after='.(int) $migration->shouldRun()."\n";
