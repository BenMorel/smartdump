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
}
