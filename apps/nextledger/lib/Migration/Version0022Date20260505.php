<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * NextLedger 1.6.3 schema additions:
 *  - Per-company logo (single upload, three layout sizes)
 *  - Per-company invoice format (regular PDF or ZUGFeRD EN16931)
 *  - Country code on company and customer (required by EN16931)
 *  - VAT-ID on customer (required by EN16931 for B2B)
 */
class Version0022Date20260505 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_company')) {
            $table = $schema->getTable('nl_settings_company');

            // Logo (Base64 payload, MIME type, layout size)
            if (!$table->hasColumn('logo_data')) {
                $table->addColumn('logo_data', 'text', [
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('logo_mime')) {
                $table->addColumn('logo_mime', 'string', [
                    'length' => 64,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('logo_size')) {
                $table->addColumn('logo_size', 'string', [
                    'length' => 16,
                    'notnull' => false,
                    'default' => 'medium',
                ]);
            }

            // ZUGFeRD / E-Rechnung
            if (!$table->hasColumn('invoice_format')) {
                $table->addColumn('invoice_format', 'string', [
                    'length' => 16,
                    'notnull' => false,
                    'default' => 'pdf',
                ]);
            }
            if (!$table->hasColumn('country_code')) {
                $table->addColumn('country_code', 'string', [
                    'length' => 2,
                    'notnull' => false,
                    'default' => 'DE',
                ]);
            }
        }

        if ($schema->hasTable('nl_customers')) {
            $table = $schema->getTable('nl_customers');
            if (!$table->hasColumn('country_code')) {
                $table->addColumn('country_code', 'string', [
                    'length' => 2,
                    'notnull' => false,
                    'default' => 'DE',
                ]);
            }
            if (!$table->hasColumn('vat_id')) {
                $table->addColumn('vat_id', 'string', [
                    'length' => 100,
                    'notnull' => false,
                ]);
            }
        }

        return $schema;
    }
}
