<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0005Date20260207 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_company')) {
            $table = $schema->getTable('nl_settings_company');
            if (!$table->hasColumn('owner_name')) {
                $table->addColumn('owner_name', 'string', ['length' => 255, 'notnull' => false]);
            }
        }

        return $schema;
    }
}
