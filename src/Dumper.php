<?php

declare(strict_types=1);

namespace BenMorel\SmartDump;

use BenMorel\SmartDump\Object\Table;
use Generator;

class Dumper
{
    private Driver $driver;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
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
     * reading rows in the workset once by once; these are two areas that can probably be improved to make the dump
     * faster.
     *
     * @param Table[] $tables             The base tables to dump.
     * @param bool    $schemaNameInOutput Whether to include the schema name in the dump. Setting this to false allows
     *                                    for importing the dump into another schema name than the source database.
     *                                    Note that if set to false and the source spans multiple schemas, importing the
     *                                    dump will regroup all tables in the same schema, and conflicts may occur if
     *                                    tables from different schemas had the same name.
     * @param bool    $dropTableIfExists  Whether to include a statement to drop the table if it already exists.
     *
     * @return Generator<string>
     */
    public function dump(array $tables, bool $schemaNameInOutput, bool $dropTableIfExists): Generator
    {
        $workset = $this->generateWorkset($tables);

        foreach ($workset->getTables() as $table) {
            $tableName = $schemaNameInOutput
                ? $this->driver->getTableIdentifier($table)
                : $this->driver->quoteIdentifier($table->name);

            if ($dropTableIfExists) {
                yield $this->driver->getDropTableIfExistsSQL($tableName);
            }

            yield $this->driver->getCreateTableSQL($table, $schemaNameInOutput);

            foreach ($workset->getRows($table) as $row) {
                $row = $this->driver->readRow($table, $row);

                yield $this->getInsertSQL($tableName, $row);
            }
        }
    }

    private function generateWorkset(array $tables): Workset
    {
        // start with a blank workset, and fill it by iterating recursively over the tables
        $workset = new Workset();

        foreach ($tables as $table) {
            foreach ($this->driver->readTable($table) as $row) {
                $this->addRowToWorkset($workset, $table, $row);
            }
        }

        return $workset;
    }

    /**
     * @psalm-param array<string, scalar|null> $row
     */
    private function addRowToWorkset(Workset $workset, Table $table, array $row): void
    {
        // @todo don't load PK columns for each row, store them in Table or cache them
        $primaryKeyColumns = $this->driver->getPrimaryKeyColumns($table);

        $primaryKeyId = [];

        foreach ($primaryKeyColumns as $column) {
            $primaryKeyId[$column] = $row[$column];
        }

        /** @psalm-suppress InvalidScalarArgument */
        if (! $workset->addRow($table, $primaryKeyId)) {
            // row already processed
            return;
        }

        // this is the first time we encounter this row, follow its relationships
        // @todo same as above
        $foreignKeys = $this->driver->getForeignKeysForTable($table);

        foreach ($foreignKeys as $foreignKey) {
            $refId = [];

            foreach ($foreignKey->columns as $columnName => $refColumnName) {
                if ($row[$columnName] === null) {
                    // no foreign record
                    continue 2;
                }

                $refId[$refColumnName] = $row[$columnName];
            }

            /** @psalm-suppress InvalidScalarArgument */
            $refRow = $this->driver->readRow($foreignKey->referencedTable, $refId);

            $this->addRowToWorkset($workset, $foreignKey->referencedTable, $refRow);
        }
    }

    /**
     * Returns a SQL statement to create the given row.
     *
     * @param string                     $table The quoted table name.
     * @param array<string, scalar|null> $row   The row, as an associative array of column names to values.
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
