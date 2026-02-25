<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0014Date20260225 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('nl_fiscal_years')) {
            $table = $schema->getTable('nl_fiscal_years');
            if (!$table->hasColumn('is_active')) {
                $table->addColumn('is_active', 'boolean', ['notnull' => false, 'default' => false]);
            }
        }

        return $schema;
    }
}
