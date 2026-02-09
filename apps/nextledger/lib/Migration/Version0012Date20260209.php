<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0012Date20260209 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_incomes')) {
            $table = $schema->getTable('nl_incomes');
            if (!$table->hasColumn('name')) {
                $table->addColumn('name', 'string', [
                    'notnull' => false,
                    'length' => 255,
                ]);
            }
        }

        return $schema;
    }
}
