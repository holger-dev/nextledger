<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0006Date20260207 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_texts')) {
            $table = $schema->getTable('nl_settings_texts');
            if (!$table->hasColumn('offer_closing_text')) {
                $table->addColumn('offer_closing_text', 'text', ['notnull' => false]);
            }
            if (!$table->hasColumn('invoice_closing_text')) {
                $table->addColumn('invoice_closing_text', 'text', ['notnull' => false]);
            }
        }

        return $schema;
    }
}
