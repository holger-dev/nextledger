<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0002Date20260207 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_cases')) {
            $table = $schema->getTable('nl_cases');
            if (!$table->hasColumn('case_number')) {
                $table->addColumn('case_number', 'string', ['length' => 64, 'notnull' => false]);
                $table->addIndex(['case_number'], 'nl_cases_number');
            }
        }

        return $schema;
    }
}
