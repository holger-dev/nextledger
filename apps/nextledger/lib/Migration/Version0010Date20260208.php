<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0010Date20260208 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_customers')) {
            $table = $schema->getTable('nl_customers');
            if (!$table->hasColumn('send_invoice_to_billing_email')) {
                $table->addColumn('send_invoice_to_billing_email', 'boolean', ['notnull' => false]);
            }
            if (!$table->hasColumn('send_invoice_to_contact_email')) {
                $table->addColumn('send_invoice_to_contact_email', 'boolean', ['notnull' => false]);
            }
        }

        return $schema;
    }
}
