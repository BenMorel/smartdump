<?php

declare(strict_types=1);

namespace BenMorel\SmartDump\Driver;

use BenMorel\SmartDump\Driver;
use BenMorel\SmartDump\Object\ForeignKey;
use BenMorel\SmartDump\Object\Table;
use PDO;

final class MySQLDriver implements Driver
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function beginTransaction(): void
    {
        $this->pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $this->pdo->exec('START TRANSACTION READ ONLY');
    }

    public function endTransaction(): void
    {
        $this->pdo->exec('COMMIT');
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
    public function getForeignKeys(Table $table): array
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

        if ($schemaNameInOutput) {
            // MySQL never includes the schema name in the SHOW CREATE TABLE output.

            $schema = $this->quoteIdentifier($table->schema);

            $sql = preg_replace('/^CREATE TABLE /', 'CREATE TABLE ' . $schema . '.', $sql);
        }

        // Constraints may or may not contain the schema of the target table, depending on whether or not the tables are
        // in the same schema. We need to rewrite the REFERENCES part to ensure that we comply with $schemaNameInOutput.
        // A regexp is not completely foolproof for this purpose, but unless table or column names contain crazy
        // characters like backticks, dots or parentheses (unfortunately they can), this will work.

        $regexp = '/(CONSTRAINT .+? FOREIGN KEY .+? REFERENCES )(?:`(.+?)`(?:\.`(.+?)`)?)( \(.+?\))/';

        $sql = preg_replace_callback($regexp, function(array $matches) use ($table, $schemaNameInOutput) {
            /** @psalm-var list<string> $matches */
            [, $before, $a, $b, $after] = $matches;

            if ($b === '') {
                $schemaName = $table->schema;
                $tableName = $a;
            } else {
                $schemaName = $a;
                $tableName = $b;
            }

            $quotedTableName = $this->quoteIdentifier($tableName);

            if ($schemaNameInOutput) {
                $quotedTableName = $this->quoteIdentifier($schemaName) . '.' . $quotedTableName;
            }

            return $before . $quotedTableName . $after;
        }, $sql);

        return $sql;
    }

    public function getDropTableIfExistsSQL(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $table . ';';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisableForeignKeysSQL(): string
    {
        return 'SET foreign_key_checks = 0;';
    }

    /**
     * {@inheritdoc}
     */
    public function getEnableForeignKeysSQL(): string
    {
        return 'SET foreign_key_checks = 1;';
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
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->pdo->quote($value);
    }
}
