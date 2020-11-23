<?php

declare(strict_types=1);

namespace BenMorel\SmartDump;

use BenMorel\SmartDump\Object\ForeignKey;
use BenMorel\SmartDump\Object\Table;

interface Driver
{
    /**
     * Begins a transaction, read-only if possible.
     * The transaction semantics should allow a consistent snapshot.
     */
    public function beginTransaction(): void;

    /**
     * Ends the transaction. This can perform a commit or a rollback, it should not matter.
     */
    public function endTransaction(): void;

    /**
     * Returns the primary key column names for the given table.
     *
     * @return string[]
     */
    public function getPrimaryKeyColumns(Table $table): array;

    /**
     * Returns the foreign keys declared on the given table.
     *
     * @return ForeignKey[]
     */
    public function getForeignKeys(Table $table): array;

    /**
     * Returns the SQL that creates the given table.
     *
     * @param bool $schemaNameInOutput Whether to include the schema name in the CREATE TABLE output.
     */
    public function getCreateTableSQL(Table $table, bool $schemaNameInOutput): string;

    /**
     * Returns the SQL to drop the table if it already exists.
     *
     * @param string $table The quoted table name.
     */
    public function getDropTableIfExistsSQL(string $table): string;

    /**
     * Returns a SQL statement that disables foreign key checks.
     */
    public function getDisableForeignKeysSQL(): string;

    /**
     * Returns a SQL statement that enables foreign key checks.
     */
    public function getEnableForeignKeysSQL(): string;

    /**
     * Returns a SQL statement to upsert the given row.
     *
     * An upsert is an INSERT that falls back to an UPDATE if a row with the same primary key already exists.
     *
     * The upsert must be implemented in such a way that is does not remove the existing row, if any, before updating
     * it.
     *
     * @psalm-param non-empty-array<string, scalar|null> $row
     *
     * @param string $table The quoted table name.
     * @param array  $row   The row, as an associative array of column names to values.
     */
    public function getUpsertSQL(string $table, array $row): string;

    /**
     * Quotes an identifier such as a table name or field name.
     *
     * Example for MySQL: 'foo' => '`foo`'
     */
    public function quoteIdentifier(string $name) : string;

    /**
     * Returns a quoted table identifier for the given table.
     *
     * Example for MySQL: '`schema`.`table`'
     */
    public function getTableIdentifier(Table $table): string;

    /**
     * Quotes the given value, if required, to be able to use it as is in an INSERT statement.
     *
     * @param scalar|null $value
     */
    public function quoteValue($value) : string;
}
