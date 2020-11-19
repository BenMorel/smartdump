<?php

declare(strict_types=1);

namespace BenMorel\SmartDump;

use BenMorel\SmartDump\Object\Table;

/**
 * Represents the total set of tables and rows to export.
 *
 * This is currently held in memory, but it should be fairly easy to refactor this to store the workset as a temporary
 * table in the database itself in the future, and iterate through it with a generator.
 */
final class Workset
{
    /**
     * The primary key ids of the rows in the workset, indexed by schema name & table name.
     *
     * @psalm-var array<string, array<string, list<non-empty-array<string, int|string>>>>
     */
    private array $primaryKeyIds = [];

    /**
     * A map of hash of existing rows to true.
     *
     * This is a quick & dirty way to check if a row is already in the workset.
     * Hashes are currently a serialized string of schema name, table name and primary key id.
     *
     * @psalm-var array<string, true>
     */
    private array $hashes = [];

    /**
     * A map of hash to Table instance.
     *
     * This allows to quickly check if a table is already set.
     * Hashes are currently a serialized string of schema name, table name.
     *
     * @var array<string, Table>
     */
    private array $tables = [];

    /**
     * Adds a table to the workset, to ensure that its structure will be exported.
     *
     * This only needs to be called for explicitly requested tables, to ensure that we'll always export their structure,
     * even if they're empty. Other tables will only be included in the workset if at least one row is required.
     *
     * @param Table $table
     */
    public function addTable(Table $table): void
    {
        $hash = serialize([$table->schema, $table->name]);

        if (! isset($this->tables[$hash])) {
            $this->tables[$hash] = $table;
        }
    }

    /**
     * Adds a table row to the workset.
     *
     * The $id is the identifier of the row as an associative array of column name to value.
     * The column names must match the primary key of the table, and be ordered according to the PK order.
     *
     * Return true if the row was added to the workset, false if it already existed.
     *
     * @psalm-param non-empty-array<string, int|string> $primaryKeyId
     */
    public function addRow(Table $table, array $primaryKeyId): bool
    {
        $hash = serialize([$table->schema, $table->name, $primaryKeyId]);

        if (isset($this->hashes[$hash])) {
            return false;
        }

        $this->addTable($table);

        $this->primaryKeyIds[$table->schema][$table->name][] = $primaryKeyId;
        $this->hashes[$hash] = true;

        return true;
    }

    /**
     * Returns the tables in the workset.
     *
     * @return Table[]
     */
    public function getTables(): array
    {
        return array_values($this->tables);
    }

    /**
     * Returns the primary key ids of the rows in the workset for the given table.
     *
     * Each identifier is an associative array of column name to value, that match the primary key of the table.
     *
     * @psalm-return list<non-empty-array<string, int|string>>
     */
    public function getPrimaryKeyIds(Table $table): array
    {
        return $this->primaryKeyIds[$table->schema][$table->name] ?? [];
    }
}
