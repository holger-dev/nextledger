<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0019Date20260325 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_settings_company')) {
            $table = $schema->getTable('nl_settings_company');
            if (!$table->hasColumn('currency_code')) {
                $table->addColumn('currency_code', 'string', [
                    'length' => 3,
                    'notnull' => false,
                ]);
            }
        }

        return $schema;
    }
}
