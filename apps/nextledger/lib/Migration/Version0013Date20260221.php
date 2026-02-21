<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0013Date20260221 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        $this->addCompanyColumn($schema, 'nl_settings_texts');
        $this->addCompanyColumn($schema, 'nl_settings_tax');
        $this->addCompanyColumn($schema, 'nl_settings_misc');
        $this->addCompanyColumn($schema, 'nl_customers');
        $this->addCompanyColumn($schema, 'nl_cases');
        $this->addCompanyColumn($schema, 'nl_case_elements');
        $this->addCompanyColumn($schema, 'nl_products');
        $this->addCompanyColumn($schema, 'nl_invoices');
        $this->addCompanyColumn($schema, 'nl_invoice_items');
        $this->addCompanyColumn($schema, 'nl_offers');
        $this->addCompanyColumn($schema, 'nl_offer_items');
        $this->addCompanyColumn($schema, 'nl_fiscal_years');
        $this->addCompanyColumn($schema, 'nl_incomes');
        $this->addCompanyColumn($schema, 'nl_expenses');

        if (!$schema->hasTable('nl_company_context')) {
            $table = $schema->createTable('nl_company_context');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'string', ['length' => 128, 'notnull' => true]);
            $table->addColumn('active_company_id', 'integer', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id'], 'nl_company_context_user_unique');
        }

        if (!$schema->hasTable('nl_settings_documents')) {
            $table = $schema->createTable('nl_settings_documents');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('company_id', 'integer', ['notnull' => true, 'default' => 1]);
            $table->addColumn('auto_store_pdfs', 'boolean', ['notnull' => false, 'default' => false]);
            $table->addColumn('keep_pdf_versions', 'boolean', ['notnull' => false, 'default' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['company_id'], 'nl_settings_documents_company');
        }

        return $schema;
    }

    private function addCompanyColumn(ISchemaWrapper $schema, string $tableName): void {
        if (!$schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->getTable($tableName);
        if ($table->hasColumn('company_id')) {
            return;
        }

        $table->addColumn('company_id', 'integer', ['notnull' => true, 'default' => 1]);
        $table->addIndex(['company_id'], $tableName . '_company');
    }
}
