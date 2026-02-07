<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0003Date20260207 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_texts')) {
            $table = $schema->getTable('nl_settings_texts');
            if (!$table->hasColumn('offer_email_subject')) {
                $table->addColumn('offer_email_subject', 'text', ['notnull' => false]);
            }
            if (!$table->hasColumn('offer_email_body')) {
                $table->addColumn('offer_email_body', 'text', ['notnull' => false]);
            }
        }

        if ($schema->hasTable('nl_offers')) {
            $table = $schema->getTable('nl_offers');
            if (!$table->hasColumn('pdf_generated_at')) {
                $table->addColumn('pdf_generated_at', 'integer', ['notnull' => false]);
            }
        }

        return $schema;
    }
}
