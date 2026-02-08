<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0007Date20260208 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_customers')) {
            $table = $schema->getTable('nl_customers');
            if (!$table->hasColumn('billing_email')) {
                $table->addColumn('billing_email', 'string', ['length' => 255, 'notnull' => false]);
            }
        }

        // Removed case-specific invoice recipient flags (now stored per customer).

        return $schema;
    }
}
