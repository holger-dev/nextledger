<?php

declare(strict_types=1);

namespace OCA\NextLedger\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20260207 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        $this->createSettingsTables($schema);
        $this->createCoreTables($schema);
        $this->createDocumentTables($schema);
        $this->createFiscalTables($schema);
        $this->createCounterTable($schema);

        return $schema;
    }

    private function createSettingsTables(ISchemaWrapper $schema): void {
        if (!$schema->hasTable('nl_settings_company')) {
            $table = $schema->createTable('nl_settings_company');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('street', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('house_number', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('zip', 'string', ['length' => 20, 'notnull' => false]);
            $table->addColumn('city', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('email', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('phone', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('vat_id', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('tax_id', 'string', ['length' => 100, 'notnull' => false]);
            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('nl_settings_texts')) {
            $table = $schema->createTable('nl_settings_texts');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('invoice_greeting', 'text', ['notnull' => false]);
            $table->addColumn('offer_greeting', 'text', ['notnull' => false]);
            $table->addColumn('footer_text', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('nl_settings_tax')) {
            $table = $schema->createTable('nl_settings_tax');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('vat_rate_bp', 'integer', ['notnull' => false]);
            $table->addColumn('is_small_business', 'boolean', ['notnull' => false, 'default' => false]);
            $table->addColumn('small_business_note', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('nl_settings_misc')) {
            $table = $schema->createTable('nl_settings_misc');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('payment_terms_days', 'integer', ['notnull' => false]);
            $table->addColumn('bank_name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('iban', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('bic', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('account_holder', 'string', ['length' => 255, 'notnull' => false]);
            $table->setPrimaryKey(['id']);
        }
    }

    private function createCoreTables(ISchemaWrapper $schema): void {
        if (!$schema->hasTable('nl_customers')) {
            $table = $schema->createTable('nl_customers');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('company', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('contact_name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('street', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('house_number', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('zip', 'string', ['length' => 20, 'notnull' => false]);
            $table->addColumn('city', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('email', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('nl_cases')) {
            $table = $schema->createTable('nl_cases');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('customer_id', 'integer', ['notnull' => false]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('deck_link', 'string', ['length' => 512, 'notnull' => false]);
            $table->addColumn('kollektiv_link', 'string', ['length' => 512, 'notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['customer_id'], 'nl_cases_customer');
        }

        if (!$schema->hasTable('nl_case_elements')) {
            $table = $schema->createTable('nl_case_elements');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('case_id', 'integer', ['notnull' => false]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('note', 'text', ['notnull' => false]);
            $table->addColumn('attachment_path', 'string', ['length' => 512, 'notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['case_id'], 'nl_case_elements_case');
        }

        if (!$schema->hasTable('nl_products')) {
            $table = $schema->createTable('nl_products');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('unit_price_cents', 'integer', ['notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
        }
    }

    private function createDocumentTables(ISchemaWrapper $schema): void {
        if (!$schema->hasTable('nl_invoices')) {
            $table = $schema->createTable('nl_invoices');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('case_id', 'integer', ['notnull' => false]);
            $table->addColumn('customer_id', 'integer', ['notnull' => false]);
            $table->addColumn('number', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('issue_date', 'integer', ['notnull' => false]);
            $table->addColumn('due_date', 'integer', ['notnull' => false]);
            $table->addColumn('greeting_text', 'text', ['notnull' => false]);
            $table->addColumn('extra_text', 'text', ['notnull' => false]);
            $table->addColumn('footer_text', 'text', ['notnull' => false]);
            $table->addColumn('subtotal_cents', 'integer', ['notnull' => false]);
            $table->addColumn('tax_cents', 'integer', ['notnull' => false]);
            $table->addColumn('total_cents', 'integer', ['notnull' => false]);
            $table->addColumn('tax_rate_bp', 'integer', ['notnull' => false]);
            $table->addColumn('is_small_business', 'boolean', ['notnull' => false, 'default' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['case_id'], 'nl_invoices_case');
            $table->addIndex(['customer_id'], 'nl_invoices_customer');
        }

        if (!$schema->hasTable('nl_invoice_items')) {
            $table = $schema->createTable('nl_invoice_items');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('invoice_id', 'integer', ['notnull' => false]);
            $table->addColumn('product_id', 'integer', ['notnull' => false]);
            $table->addColumn('position_type', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('quantity', 'integer', ['notnull' => false]);
            $table->addColumn('unit_price_cents', 'integer', ['notnull' => false]);
            $table->addColumn('total_cents', 'integer', ['notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['invoice_id'], 'nl_invoice_items_invoice');
        }

        if (!$schema->hasTable('nl_offers')) {
            $table = $schema->createTable('nl_offers');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('case_id', 'integer', ['notnull' => false]);
            $table->addColumn('customer_id', 'integer', ['notnull' => false]);
            $table->addColumn('number', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('issue_date', 'integer', ['notnull' => false]);
            $table->addColumn('valid_until', 'integer', ['notnull' => false]);
            $table->addColumn('greeting_text', 'text', ['notnull' => false]);
            $table->addColumn('extra_text', 'text', ['notnull' => false]);
            $table->addColumn('footer_text', 'text', ['notnull' => false]);
            $table->addColumn('subtotal_cents', 'integer', ['notnull' => false]);
            $table->addColumn('tax_cents', 'integer', ['notnull' => false]);
            $table->addColumn('total_cents', 'integer', ['notnull' => false]);
            $table->addColumn('tax_rate_bp', 'integer', ['notnull' => false]);
            $table->addColumn('is_small_business', 'boolean', ['notnull' => false, 'default' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['case_id'], 'nl_offers_case');
            $table->addIndex(['customer_id'], 'nl_offers_customer');
        }

        if (!$schema->hasTable('nl_offer_items')) {
            $table = $schema->createTable('nl_offer_items');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('offer_id', 'integer', ['notnull' => false]);
            $table->addColumn('product_id', 'integer', ['notnull' => false]);
            $table->addColumn('position_type', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('quantity', 'integer', ['notnull' => false]);
            $table->addColumn('unit_price_cents', 'integer', ['notnull' => false]);
            $table->addColumn('total_cents', 'integer', ['notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['offer_id'], 'nl_offer_items_offer');
        }
    }

    private function createFiscalTables(ISchemaWrapper $schema): void {
        if (!$schema->hasTable('nl_fiscal_years')) {
            $table = $schema->createTable('nl_fiscal_years');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('date_start', 'integer', ['notnull' => false]);
            $table->addColumn('date_end', 'integer', ['notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('nl_incomes')) {
            $table = $schema->createTable('nl_incomes');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('fiscal_year_id', 'integer', ['notnull' => false]);
            $table->addColumn('invoice_id', 'integer', ['notnull' => false]);
            $table->addColumn('amount_cents', 'integer', ['notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('booked_at', 'integer', ['notnull' => false]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['fiscal_year_id'], 'nl_incomes_year');
            $table->addIndex(['invoice_id'], 'nl_incomes_invoice');
        }

        if (!$schema->hasTable('nl_expenses')) {
            $table = $schema->createTable('nl_expenses');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('fiscal_year_id', 'integer', ['notnull' => false]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('amount_cents', 'integer', ['notnull' => false]);
            $table->addColumn('booked_at', 'integer', ['notnull' => false]);
            $table->addColumn('created_at', 'integer', ['notnull' => false]);
            $table->addColumn('updated_at', 'integer', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['fiscal_year_id'], 'nl_expenses_year');
        }
    }

    private function createCounterTable(ISchemaWrapper $schema): void {
        if ($schema->hasTable('nl_counters')) {
            return;
        }

        $table = $schema->createTable('nl_counters');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('counter_key', 'string', ['length' => 64, 'notnull' => true]);
        $table->addColumn('counter_value', 'integer', ['notnull' => true, 'default' => 0]);
        $table->addColumn('updated_at', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['counter_key'], 'nl_counters_key');
    }
}
