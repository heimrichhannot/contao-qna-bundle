<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if ('1' !== getenv('QNA_DATABASE_TESTS')) {
    throw new RuntimeException('Set QNA_DATABASE_TESTS=1 and select an empty disposable database.');
}

$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => getenv('QNA_TEST_DB_HOST') ?: 'db',
    'port' => (int) (getenv('QNA_TEST_DB_PORT') ?: 3306),
    'dbname' => getenv('QNA_TEST_DB_NAME') ?: 'db',
    'user' => getenv('QNA_TEST_DB_USER') ?: 'db',
    'password' => getenv('QNA_TEST_DB_PASSWORD') ?: 'db',
]);
$schema = new Schema();
foreach (['tl_qna_session', 'tl_qna_question', 'tl_qna_vote'] as $name) {
    require dirname(__DIR__, 2).'/contao/dca/'.$name.'.php';
    /** @var array{fields: array<string, array{sql: array{type: string}}>, config: array{sql: array{keys: array<string, string>}}} $dca */
    $dca = ((array) $GLOBALS['TL_DCA'])[$name];
    $table = $schema->createTable($name);
    $table->addOption('engine', 'InnoDB');
    foreach ($dca['fields'] as $field => $definition) {
        $options = $definition['sql'];
        $type = $options['type'];
        unset($options['type']);
        $table->addColumn($field, $type, $options);
    }
    foreach ($dca['config']['sql']['keys'] as $fields => $kind) {
        $columns = explode(',', $fields);
        match ($kind) {
            'primary' => $table->setPrimaryKey($columns),
            'unique' => $table->addUniqueIndex($columns),
            'index' => $table->addIndex($columns),
            default => throw new LogicException('Unsupported DCA index kind: '.$kind),
        };
    }
}
// CREATE only: never silently replace existing demo/application tables.
foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
echo "Created three InnoDB tables from the bundle DCA definitions.\n";
