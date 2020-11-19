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
}
