<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0020Date20260325 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_cases')) {
            $table = $schema->getTable('nl_cases');
            if (!$table->hasColumn('is_archived')) {
                $table->addColumn('is_archived', 'boolean', [
                    'notnull' => false,
                    'default' => false,
                ]);
            }
            if (!$table->hasIndex('nl_cases_archived_idx')) {
                $table->addIndex(['is_archived'], 'nl_cases_archived_idx');
            }
        }

        return $schema;
    }
}
