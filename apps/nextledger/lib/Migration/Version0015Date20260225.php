<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0015Date20260225 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_email')) {
            $table = $schema->getTable('nl_settings_email');

            if (!$table->hasColumn('company_id')) {
                $table->addColumn('company_id', 'integer', [
                    'notnull' => false,
                    'unsigned' => true,
                ]);
            }
            if (!$table->hasColumn('provider_id')) {
                $table->addColumn('provider_id', 'string', [
                    'length' => 255,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('service_id')) {
                $table->addColumn('service_id', 'string', [
                    'length' => 255,
                    'notnull' => false,
                ]);
            }

            if (!$table->hasIndex('nl_settings_email_user_company')) {
                $table->addIndex(['user_id', 'company_id'], 'nl_settings_email_user_company');
            }
        }

        return $schema;
    }
}
