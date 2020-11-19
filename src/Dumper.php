<?php

declare(strict_types=1);

namespace BenMorel\SmartDump;

use BenMorel\SmartDump\Object\Table;
use Generator;
use PDO;

class Dumper
{
    private PDO $pdo;

    private Driver $driver;

    private DriverCache $driverCache;

    public function __construct(PDO $pdo, Driver $driver)
    {
        $this->pdo = $pdo;
        $this->driver = $driver;
        $this->driverCache = new DriverCache($driver);
    }

    /**
     * Dumps the given tables and all their relationships.
     *
     * Only the given tables will be dumped in full, related tables that are not in the given set of tables will only
     * contain the required rows to satisfy the foreign keys.
     *
     * Every string returned by the generator is a SQL statement.
     *
     * Note that we're iterating the tables twice, once to generate the workset, and once to dump the rows. We're also
     * reading rows in the workset one by one; these are two areas that can probably be improved to make the dump
     * faster.
     *
     * @param Table[] $tables The base tables to dump.
     *
     * @return Generator<string>
     */
    public function dump(array $tables, DumpOptions $options): Generator
    {
        $workset = $this->generateWorkset($tables);

        // even though our export should be referentially intact, our inserts are not necessarily performed in the right
        // order, so we disable foreign key checks first!
        yield $this->driver->getDisableForeignKeysSQL();

        foreach ($workset->getTables() as $table) {
            $tableName = $options->includeSchemaNameInOutput
                ? $this->driver->getTableIdentifier($table)
                : $this->driver->quoteIdentifier($table->name);

            if ($options->addDropTable) {
                yield $this->driver->getDropTableIfExistsSQL($tableName);
            }

            if ($options->addCreateTable) {
                yield $this->driver->getCreateTableSQL($table, $options->includeSchemaNameInOutput);
            }

            foreach ($workset->getPrimaryKeyIds($table) as $primaryKeyId) {
                $row = $this->readRow($table, $primaryKeyId);

                yield $this->getInsertSQL($tableName, $row);
            }
        }

        yield $this->driver->getEnableForeignKeysSQL();
    }

    /**
     * @param Table[] $tables
     */
    private function generateWorkset(array $tables): Workset
    {
        // start with a blank workset, and fill it by iterating recursively over the table rows
        $workset = new Workset();

        foreach ($tables as $table) {
            foreach ($this->readTable($table) as $row) {
                $this->addRowToWorkset($workset, $table, $row);
            }
        }

        return $workset;
    }

    /**
     * @psalm-param non-empty-array<string, scalar|null> $row
     */
    private function addRowToWorkset(Workset $workset, Table $table, array $row): void
    {
        $primaryKeyColumns = $this->driverCache->getPrimaryKeyColumns($table);

        $primaryKeyId = [];

        foreach ($primaryKeyColumns as $column) {
            $primaryKeyId[$column] = $row[$column];
        }

        /** @psalm-var non-empty-array<string, int|string> $primaryKeyId */
        if (! $workset->addRow($table, $primaryKeyId)) {
            // row already processed
            return;
        }

        // this is the first time we encounter this row, follow its relationships
        $foreignKeys = $this->driverCache->getForeignKeys($table);

        foreach ($foreignKeys as $foreignKey) {
            $refId = [];

            foreach ($foreignKey->columns as $columnName => $refColumnName) {
                if ($row[$columnName] === null) {
                    // no foreign record
                    continue 2;
                }

                $refId[$refColumnName] = $row[$columnName];
            }

            /** @psalm-var non-empty-array<string, int|string> $refId */
            $refRow = $this->readRow($foreignKey->referencedTable, $refId);

            $this->addRowToWorkset($workset, $foreignKey->referencedTable, $refRow);
        }
    }

    /**
     * Reads the whole table, yielding rows as associative arrays.
     *
     * @psalm-return Generator<non-empty-array<string, scalar|null>>
     */
    private function readTable(Table $table): Generator
    {
        $query = 'SELECT * FROM ' . $this->driver->getTableIdentifier($table);

        $statement = $this->pdo->query($query);

        while (false !== $row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /** @psalm-var non-empty-array<string, scalar|null> $row */
            yield $row;
        }
    }

    /**
     * Reads a single row as an associative array of column name to value.
     *
     * The $uniqueId is the identifier of the row as an associative array of column name to value.
     * The column names must match the primary key or a unique key of the table.
     *
     * @psalm-param non-empty-array<string, int|string> $uniqueId
     *
     * @psalm-return non-empty-array<string, scalar|null>
     */
    public function readRow(Table $table, array $uniqueId): array
    {
        $conditions = [];
        $values = [];

        foreach ($uniqueId as $name => $value) {
            $conditions[] = $this->driver->quoteIdentifier($name) . ' = ?';
            $values[] = $value;
        }

        $query = sprintf(
            'SELECT * FROM %s WHERE %s',
            $this->driver->getTableIdentifier($table),
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
     * Returns a SQL statement to create the given row.
     *
     * @psalm-param non-empty-array<string, scalar|null> $row
     *
     * @param string $table The quoted table name.
     * @param array  $row   The row, as an associative array of column names to values.
     */
    private function getInsertSQL(string $table, array $row): string
    {
        $keys = [];
        $values = [];

        foreach ($row as $key => $value) {
            $keys[] = $this->driver->quoteIdentifier($key);
            $values[] = $this->driver->quoteValue($value);
        }

        $keys = implode(', ', $keys);
        $values = implode(', ', $values);

        return sprintf('INSERT INTO %s (%s) VALUES(%s);', $table, $keys, $values);
    }
}
