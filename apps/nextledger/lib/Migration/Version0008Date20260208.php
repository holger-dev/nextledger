<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0008Date20260208 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_invoices')) {
            $table = $schema->getTable('nl_invoices');
            if (!$table->hasColumn('invoice_type')) {
                $table->addColumn('invoice_type', 'string', ['length' => 32, 'notnull' => false]);
            }
            if (!$table->hasColumn('related_offer_id')) {
                $table->addColumn('related_offer_id', 'integer', ['notnull' => false]);
                $table->addIndex(['related_offer_id'], 'nl_invoices_related_offer');
            }
            if (!$table->hasColumn('service_period_start')) {
                $table->addColumn('service_period_start', 'integer', ['notnull' => false]);
            }
            if (!$table->hasColumn('service_period_end')) {
                $table->addColumn('service_period_end', 'integer', ['notnull' => false]);
            }
        }

        return $schema;
    }
}
