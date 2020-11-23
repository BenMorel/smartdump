<?php

declare(strict_types=1);

namespace BenMorel\SmartDump\Configuration;

use BenMorel\SmartDump\Object\Table;

class TargetTable
{
    /**
     * The table to dump.
     */
    public Table $table;

    /**
     * The extra conditions for this table, if any.
     *
     * These conditions will be appended to the `SELECT * FROM <table>` query.
     *
     * Some examples:
     *  - "LIMIT 10"
     *  - "WHERE user_id = 123"
     *  - "WHERE user_id = 123 ORDER BY id DESC LIMIT 10"
     */
    public ?string $conditions;
}
