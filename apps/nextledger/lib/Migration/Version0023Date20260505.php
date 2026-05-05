<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * NextLedger 1.6.3 — choose which attachment(s) to include when an invoice
 * is sent by email: PDF, ZUGFeRD-XML, or both. Defaults to PDF (legacy
 * behaviour).
 */
class Version0023Date20260505 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_company')) {
            $table = $schema->getTable('nl_settings_company');
            if (!$table->hasColumn('mail_attachment')) {
                $table->addColumn('mail_attachment', 'string', [
                    'length' => 16,
                    'notnull' => false,
                    'default' => 'pdf',
                ]);
            }
        }

        return $schema;
    }
}
