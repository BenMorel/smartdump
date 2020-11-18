<?php

declare(strict_types=1);

namespace BenMorel\SmartDump\Object;

/**
 * @psalm-immutable
 */
final class Table
{
    /**
     * The schema name where the table resides.
     */
    public string $schema;

    /**
     * The table name.
     */
    public string $name;
}
