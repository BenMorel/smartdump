<?php

declare(strict_types=1);

namespace BenMorel\SmartDump\Driver;

use BenMorel\SmartDump\Driver;
use BenMorel\SmartDump\Object\ForeignKey;
use BenMorel\SmartDump\Object\Table;
use Generator;
use PDO;

final class MySQLDriver implements Driver
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPrimaryKeyColumns(Table $table): array
    {
        $statement = $this->pdo->prepare(<<<EOT
            SELECT COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE CONSTRAINT_NAME = 'PRIMARY'
            AND TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        EOT);

        $statement->execute([$table->schema, $table->name]);

        /** @var string[] $rows */
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $rows;
    }

    /**
     * @psalm-suppress InaccessibleProperty https://github.com/vimeo/psalm/issues/4606
     */
    public function getForeignKeysForTable(Table $table): array
    {
        $statement = $this->pdo->prepare(<<<EOT
            SELECT ID, REF_NAME
            FROM information_schema.INNODB_FOREIGN
            WHERE FOR_NAME = ?
            ORDER BY REF_NAME
        EOT);

        $statement->execute([$table->schema . '/' . $table->name]);

        /** @psalm-var list<array{ID: string, REF_NAME: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $foreignKeys = [];

        foreach ($rows as $row) {
            [$refTableSchema, $refTableName] = explode('/', $row['REF_NAME']);
            [$fkSchema, $fkName] = explode('/', $row['ID']);

            $foreignKey = new ForeignKey();

            $foreignKey->schema          = $fkSchema;
            $foreignKey->name            = $fkName;
            $foreignKey->table           = $table;
            $foreignKey->referencedTable = new Table($refTableSchema, $refTableName);
            $foreignKey->columns         = $this->getForeignKeyColumns($row['ID']);

            $foreignKey->targetsPrimaryKey = $this->areColumnsTheSame(
                $foreignKey->columns,
                $this->getPrimaryKeyColumns($foreignKey->referencedTable)
            );

            $foreignKeys[] = $foreignKey;
        }

        return $foreignKeys;
    }

    public function readTable(Table $table): Generator
    {
        $statement = $this->pdo->query('SELECT * FROM ' . $this->getTableIdentifier($table));

        while (false !== $row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /** @psalm-var non-empty-array<string, scalar|null> $row */
            yield $row;
        }
    }

    public function readRow(Table $table, array $id): array
    {
        $conditions = [];
        $values = [];

        foreach ($id as $name => $value) {
            $conditions[] = $this->quoteIdentifier($name) . ' = ?';
            $values[] = $value;
        }

        $query = sprintf(
            'SELECT * FROM %s WHERE %s',
            $this->getTableIdentifier($table),
            implode(' AND ', $conditions)
        );

        $statement = $this->pdo->prepare($query);
        $statement->execute($values);

        /** @psalm-var list<non-empty-array<string, scalar|null>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        assert(count($rows) === 1);

        return $rows[0];
    }

    /**
     * @psalm-return non-empty-array<string, string>
     */
    private function getForeignKeyColumns(string $fkID): array
    {
        $statement = $this->pdo->prepare(<<<EOT
            SELECT FOR_COL_NAME, REF_COL_NAME
            FROM information_schema.INNODB_FOREIGN_COLS
            WHERE ID = ?
            ORDER BY POS
        EOT);

        $statement->execute([$fkID]);

        /** @psalm-var list<array{FOR_COL_NAME: string, REF_COL_NAME: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        assert(count($rows) !== 0);

        $result = [];

        foreach ($rows as $row) {
            $result[$row['FOR_COL_NAME']] = $row['REF_COL_NAME'];
        }

        return $result;
    }

    /**
     * Checks if the list of columns are the same, even if not in the same order.
     *
     * @param string[] $a
     * @param string[] $b
     *
     * @return bool
     */
    private function areColumnsTheSame(array $a, array $b): bool
    {
        $a = array_values($a);
        $b = array_values($b);

        sort($a);
        sort($b);

        return $a === $b;
    }

    public function getCreateTableSQL(Table $table, bool $schemaNameInOutput): string
    {
        $statement = $this->pdo->query('SHOW CREATE TABLE ' . $this->getTableIdentifier($table));

        /** @var string $sql */
        $sql = $statement->fetchColumn(1);

        assert(strpos($sql, 'CREATE TABLE') === 0);

        // output does not contain a semicolon
        $sql .= ';';

        if (! $schemaNameInOutput) {
            // MySQL never includes the schema name in the SHOW CREATE TABLE output.
            return $sql;
        }

        // There does not seem to be a way to tell MySQL to include the schema name in the CREATE TABLE output;
        // let's add it manually.

        $schema = $this->quoteIdentifier($table->schema);

        return preg_replace('/^CREATE TABLE /', 'CREATE TABLE ' . $schema . '.', $sql);
    }

    public function getDropTableIfExistsSQL(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $table . ';';
    }

    /**
     * {@inheritdoc}
     */
    public function quoteIdentifier(string $name) : string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function getTableIdentifier(Table $table): string
    {
        return $this->quoteIdentifier($table->schema) . '.' . $this->quoteIdentifier($table->name);
    }

    public function quoteValue($value) : string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return (string) (int) $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->pdo->quote($value);
    }
}
