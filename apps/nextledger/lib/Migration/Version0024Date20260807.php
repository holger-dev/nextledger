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
class Version0024Date20260807 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_texts')) {
            $table = $schema->getTable('nl_settings_texts');
            if (!$table->hasColumn('closing_greeting')) {
                $table->addColumn('closing_greeting', 'string', [
                    'length' => 255,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('signature_name')) {
                $table->addColumn('signature_name', 'string', [
                    'length' => 255,
                    'notnull' => false,
                ]);
            }
        }

        foreach (['nl_invoice_items', 'nl_offer_items'] as $itemTable) {
            if ($schema->hasTable($itemTable)) {
                $table = $schema->getTable($itemTable);
                if (!$table->hasColumn('tax_rate_bp')) {
                    // null = inherit the document-level tax rate (legacy behaviour)
                    $table->addColumn('tax_rate_bp', 'integer', [
                        'notnull' => false,
                    ]);
                }
            }
        }

        if ($schema->hasTable('nl_settings_company')) {
            $table = $schema->getTable('nl_settings_company');
            if (!$table->hasColumn('number_scheme')) {
                // e.g. "RE-{YYYY}-{SEQ4}"; null = legacy YYYYMMDD-#### scheme
                $table->addColumn('number_scheme', 'string', [
                    'length' => 64,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('doc_layout')) {
                // JSON blob with document layout preferences (field visibility,
                // logo position, company block position, font size)
                $table->addColumn('doc_layout', 'text', [
                    'notnull' => false,
                ]);
            }
        }

        if ($schema->hasTable('nl_expenses')) {
            $table = $schema->getTable('nl_expenses');
            if (!$table->hasColumn('attachment_path')) {
                $table->addColumn('attachment_path', 'string', [
                    'length' => 512,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('recurring_interval')) {
                // none | monthly | quarterly | yearly
                $table->addColumn('recurring_interval', 'string', [
                    'length' => 16,
                    'notnull' => false,
                    'default' => 'none',
                ]);
            }
            if (!$table->hasColumn('recurring_until')) {
                $table->addColumn('recurring_until', 'integer', [
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('recurring_parent_id')) {
                $table->addColumn('recurring_parent_id', 'integer', [
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('last_recurred_at')) {
                $table->addColumn('last_recurred_at', 'integer', [
                    'notnull' => false,
                ]);
            }
        }

        return $schema;
    }
}
