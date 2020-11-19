<?php

declare(strict_types=1);

namespace BenMorel\SmartDump;

use BenMorel\SmartDump\Object\ForeignKey;
use BenMorel\SmartDump\Object\Table;

/**
 * Wraps a Driver to cache its output.
 */
class DriverCache
{
    private Driver $driver;

    /**
     * A cache of the primary key columns of each table, indexed by schema name and table name.
     *
     * @psalm-var array<string, array<string, string[]>>
     */
    private array $primaryKeyColumns = [];

    /**
     * A cache of the foreign keys of each table, indexed by schema name and table name.
     *
     * @psalm-var array<string, array<string, ForeignKey[]>>
     */
    private array $foreignKeys = [];

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @return string[]
     */
    public function getPrimaryKeyColumns(Table $table): array
    {
        if (isset($this->primaryKeyColumns[$table->schema][$table->name])) {
            return $this->primaryKeyColumns[$table->schema][$table->name];
        }

        return $this->primaryKeyColumns[$table->schema][$table->name] = $this->driver->getPrimaryKeyColumns($table);
    }

    /**
     * @return ForeignKey[]
     */
    public function getForeignKeys(Table $table): array
    {
        if (isset($this->foreignKeys[$table->schema][$table->name])) {
            return $this->foreignKeys[$table->schema][$table->name];
        }

        return $this->foreignKeys[$table->schema][$table->name] = $this->driver->getForeignKeys($table);
    }
}

