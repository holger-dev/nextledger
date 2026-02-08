<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0009Date20260208 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if (!$schema->hasTable('nl_settings_email')) {
            $table = $schema->createTable('nl_settings_email');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addColumn('user_id', 'string', [
                'length' => 64,
                'notnull' => true,
            ]);
            $table->addColumn('mode', 'string', [
                'length' => 32,
                'notnull' => false,
            ]);
            $table->addColumn('from_email', 'string', [
                'length' => 255,
                'notnull' => false,
            ]);
            $table->addColumn('reply_to_email', 'string', [
                'length' => 255,
                'notnull' => false,
            ]);
            $table->addIndex(['user_id'], 'nl_settings_email_user');
        }

        return $schema;
    }
}
