<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0011Date20260208 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_cases')) {
            $table = $schema->getTable('nl_cases');
            if ($table->hasColumn('send_invoice_to_billing_email')) {
                $table->dropColumn('send_invoice_to_billing_email');
            }
            if ($table->hasColumn('send_invoice_to_contact_email')) {
                $table->dropColumn('send_invoice_to_contact_email');
            }
        }

        return $schema;
    }
}
