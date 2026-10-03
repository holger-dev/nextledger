<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * NextLedger 1.7.0 schema additions (GitHub issues #13–#24):
 *  - #15: configurable closing greeting + signature name on texts
 *  - #24: per-position VAT rate on invoice and offer items
 *  - #14: custom invoice number scheme per company
 *  - #16: file attachment path on expenses
 *  - #23: recurring expense configuration
 *  - #18/#20: document layout settings (JSON) per company
 */
class Version0025Date20261003 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_company')) {
            $table = $schema->getTable('nl_settings_company');
            if (!$table->hasColumn('number_scheme_offer')) {
                // e.g. "RE-{YYYY}-{SEQ4}"; null = legacy YYYYMMDD-#### scheme
                $table->addColumn('number_scheme_offer', 'string', [
                    'length' => 64,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('number_scheme_case')) {
                // e.g. "RE-{YYYY}-{SEQ4}"; null = legacy YYYYMMDD-#### scheme
                $table->addColumn('number_scheme_case', 'string', [
                    'length' => 64,
                    'notnull' => false,
                ]);
            }
        }
        return $schema;
    }
}
