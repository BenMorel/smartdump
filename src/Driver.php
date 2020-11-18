<?php

declare(strict_types=1);

namespace BenMorel\SmartDump;

use BenMorel\SmartDump\Object\ForeignKey;
use BenMorel\SmartDump\Object\Table;

interface Driver
{
    /**
     * Returns the foreign keys declared on the given table.
     *
     * @return ForeignKey[]
     */
    public function getForeignKeysForTable(Table $table): array;
}
