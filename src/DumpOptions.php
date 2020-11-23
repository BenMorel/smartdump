<?php

declare(strict_types=1);

namespace BenMorel\SmartDump;

class DumpOptions
{
    /**
     * Whether to add CREATE TABLE statements in the dump.
     */
    public bool $addCreateTable;

    /**
     * Whether to add a DROP TABLE IF EXITS or equivalent statement before each CREATE TABLE in the dump.
     *
     * Ignored if `$addCreateTable` is false.
     */
    public bool $addDropTable;

    /**
     * Whether to include the schema name in the dump.
     *
     * Setting this to false allows for importing the dump into a schema name other than that of the source database.
     * Note that if set to false and the source spans multiple schemas, importing the dump will regroup all tables in
     * the same schema, and conflicts may occur if tables from different schemas had the same name.
     */
    public bool $includeSchemaNameInOutput;

    /**
     * Whether to produce a dump that can be merged into an existing schema.
     *
     * This will use an upsert instead of an INSERT.
     * In MySQL, this is implemented using an INSERT ... ON DUPLICATE KEY UPDATE.
     *
     * Implies `$addCreateTable` = false and `$addDropTable = false`.
     *
     * Note that importing a dump with `$merge = true` may still fail due to unique key conflicts with existing rows in
     * the target table.
     */
    public bool $merge;
}
