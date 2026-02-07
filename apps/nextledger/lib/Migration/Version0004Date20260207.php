<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0004Date20260207 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_texts')) {
            $table = $schema->getTable('nl_settings_texts');
            if (!$table->hasColumn('invoice_email_subject')) {
                $table->addColumn('invoice_email_subject', 'text', ['notnull' => false]);
            }
            if (!$table->hasColumn('invoice_email_body')) {
                $table->addColumn('invoice_email_body', 'text', ['notnull' => false]);
            }
        }

        return $schema;
    }
}
