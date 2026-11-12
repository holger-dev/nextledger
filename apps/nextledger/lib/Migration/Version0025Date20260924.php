<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * NextLedger 1.7.3 — email delivery log. Every direct send attempt (invoice
 * or offer) is recorded with its outcome so users can diagnose delivery
 * problems (e.g. gateway rejections) without digging through server logs.
 */
class Version0025Date20260924 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if (!$schema->hasTable('nl_mail_log')) {
            $table = $schema->createTable('nl_mail_log');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('company_id', 'integer', ['notnull' => true]);
            $table->addColumn('document_type', 'string', ['length' => 16, 'notnull' => false]);
            $table->addColumn('document_id', 'integer', ['notnull' => false]);
            $table->addColumn('document_number', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('recipients', 'string', ['length' => 512, 'notnull' => false]);
            $table->addColumn('subject', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 16, 'notnull' => false]);
            $table->addColumn('error', 'text', ['notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['company_id', 'created_at'], 'nl_mail_log_company_time');
        }

        return $schema;
    }
}
