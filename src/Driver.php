<?php

declare(strict_types=1);

namespace BenMorel\SmartDump;

use BenMorel\SmartDump\Object\ForeignKey;
use BenMorel\SmartDump\Object\Table;
use Generator;

interface Driver
{
    /**
     * Returns the primary key column names for given name.
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
     * Reads the whole table, yielding rows as associative arrays.
     *
     * @todo not driver-specific
     *
     * @param Table $table
     *
     * @return Generator<non-empty-array<string, scalar|null>>
     */
    public function readTable(Table $table): Generator;

    /**
     * Reads a single row as an associative array of column name to value.
     *
     * The $id is the identifier of the row as an associative array of column name to value.
     * The column names must match the primary key or a unique key of the table.
     *
     * @todo not driver-specific
     *
     * @psalm-param non-empty-array<string, int|string> $id
     *
     * @psalm-return non-empty-array<string, scalar|null>
     */
    public function readRow(Table $table, array $id): array;

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
     * Quotes an identifier such as a table name or field name.
     *
     * Example for MySQL: 'foo' => '`foo`'
     */
    public function quoteIdentifier(string $name) : string;

    /**
     * Quotes a table identifier.
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
