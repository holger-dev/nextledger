<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0018Date20260325 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_invoices')) {
            $table = $schema->getTable('nl_invoices');
            if (!$table->hasColumn('custom_field_label')) {
                $table->addColumn('custom_field_label', 'string', [
                    'length' => 255,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('custom_field_value')) {
                $table->addColumn('custom_field_value', 'string', [
                    'length' => 255,
                    'notnull' => false,
                ]);
            }
        }

        return $schema;
    }
}
