<?php

declare(strict_types=1);

namespace BenMorel\SmartDump\Object;

/**
 * @psalm-immutable
 */
final class ForeignKey
{
    /**
     * The schema where the FK resides.
     *
     * @var string
     */
    public string $schema;

    /**
     * The FK name.
     */
    public string $name;

    /**
     * The table where the FK is declared.
     */
    public Table $table;

    /**
     * The table referenced by the FK.
     */
    public Table $referencedTable;

    /**
     * A map of column name to referenced column name.
     *
     * @psalm-var non-empty-array<string, string>
     */
    public array $columns;

    /**
     * Whether the referenced column names match the table's primary key.
     *
     * True if they match the primary key, false if they match a unique key.
     */
    public bool $targetsPrimaryKey;
}
