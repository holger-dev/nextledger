<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0017Date20260325 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_company')) {
            $table = $schema->getTable('nl_settings_company');
            if (!$table->hasColumn('owner_user_id')) {
                $table->addColumn('owner_user_id', 'string', [
                    'length' => 128,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasIndex('nl_settings_company_owner_user')) {
                $table->addIndex(['owner_user_id'], 'nl_settings_company_owner_user');
            }
        }

        if (!$schema->hasTable('nl_company_user_access')) {
            $table = $schema->createTable('nl_company_user_access');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('company_id', 'integer', ['notnull' => true]);
            $table->addColumn('user_id', 'string', ['length' => 128, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['company_id'], 'nl_company_user_access_company');
            $table->addIndex(['user_id'], 'nl_company_user_access_user');
            $table->addUniqueIndex(['company_id', 'user_id'], 'nl_company_user_access_unique');
        }

        return $schema;
    }
}
