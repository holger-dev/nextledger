<?php

declare(strict_types=1);

namespace OCA\NextLedger\Tests\Unit\Service;

use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\Customer;
use OCA\NextLedger\Db\Invoice;
use OCA\NextLedger\Db\InvoiceItem;
use OCA\NextLedger\Db\MiscSetting;
use OCA\NextLedger\Service\ZugferdXmlService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ZugferdXmlService}.
 *
 * These tests do NOT boot a Nextcloud server. They construct entities
 * directly and assert that the resulting CII-XML carries the BT-* fields
 * required by EN16931 for the values we feed in.
 */
class ZugferdXmlServiceTest extends TestCase {
    public function testBuildXmlContainsRequiredBtFields(): void {
        $service = new ZugferdXmlService();

        $company = new Company();
        $company->setName('Acme GmbH');
        $company->setStreet('Musterstraße');
        $company->setHouseNumber('1');
        $company->setZip('12345');
        $company->setCity('Berlin');
        $company->setEmail('billing@acme.example');
        $company->setVatId('DE123456789');
        $company->setCountryCode('DE');
        $company->setCurrencyCode('EUR');
        $company->setInvoiceFormat(ZugferdXmlService::FORMAT_ZUGFERD);

        $customer = new Customer();
        $customer->setCompany('Beispiel AG');
        $customer->setStreet('Kundenweg');
        $customer->setHouseNumber('7');
        $customer->setZip('80331');
        $customer->setCity('München');
        $customer->setEmail('einkauf@beispiel.example');
        $customer->setCountryCode('DE');
        $customer->setVatId('DE987654321');

        $invoice = new Invoice();
        $invoice->setNumber('20260505-0001');
        $invoice->setIssueDate(strtotime('2026-05-05 00:00:00'));
        $invoice->setDueDate(strtotime('2026-06-04 00:00:00'));
        $invoice->setIsSmallBusiness(false);
        $invoice->setTaxRateBp(1900); // 19,00 %
        $invoice->setSubtotalCents(10000); // 100,00
        $invoice->setTaxCents(1900);
        $invoice->setTotalCents(11900);

        $item = new InvoiceItem();
        $item->setName('Beratungsleistung');
        $item->setDescription('Workshop-Vorbereitung');
        $item->setQuantity(1);
        $item->setUnitPriceCents(10000);
        $item->setTotalCents(10000);

        $misc = new MiscSetting();
        $misc->setIban('DE00000000000000000000');
        $misc->setBic('TESTDEXXXXX');
        $misc->setAccountHolder('Acme GmbH');

        $xml = $service->buildXml($invoice, [$item], $customer, $company, $misc);

        $this->assertNotSame('', $xml, 'XML output must not be empty.');
        $this->assertStringContainsString('CrossIndustryInvoice', $xml, 'Output must be a CII document.');
        $this->assertStringContainsString('20260505-0001', $xml, 'Invoice number (BT-1) must be present.');
        $this->assertStringContainsString('EUR', $xml, 'Currency (BT-5) must be present.');
        $this->assertStringContainsString('Acme GmbH', $xml, 'Seller name (BT-27) must be present.');
        $this->assertStringContainsString('Beispiel AG', $xml, 'Buyer name (BT-44) must be present.');
        $this->assertStringContainsString('DE123456789', $xml, 'Seller VAT-ID must be present.');
        $this->assertStringContainsString('DE987654321', $xml, 'Buyer VAT-ID (BT-48) must be present.');
        $this->assertStringContainsString('Beratungsleistung', $xml, 'Line item name must be present.');
        $this->assertStringContainsString('119.00', $xml, 'Grand total (BT-112) must be present.');
        $this->assertStringContainsString('100.00', $xml, 'Subtotal (BT-106) must be present.');
        $this->assertStringContainsString('19.00', $xml, 'Tax amount (BT-110) must be present.');
        $this->assertStringContainsString('DE00000000000000000000', $xml, 'IBAN payment means must be present.');
    }

    public function testSmallBusinessInvoiceUsesExemptionCategory(): void {
        $service = new ZugferdXmlService();

        $company = new Company();
        $company->setName('Klein KG');
        $company->setCountryCode('DE');
        $company->setCurrencyCode('EUR');
        $company->setVatId('');
        $company->setTaxId('99/123/45678');

        $customer = new Customer();
        $customer->setCompany('Kunde GmbH');
        $customer->setCountryCode('DE');

        $invoice = new Invoice();
        $invoice->setNumber('R-1');
        $invoice->setIssueDate(strtotime('2026-05-05 00:00:00'));
        $invoice->setIsSmallBusiness(true);
        $invoice->setTaxRateBp(0);
        $invoice->setSubtotalCents(5000);
        $invoice->setTaxCents(0);
        $invoice->setTotalCents(5000);

        $item = new InvoiceItem();
        $item->setName('Service');
        $item->setQuantity(1);
        $item->setUnitPriceCents(5000);
        $item->setTotalCents(5000);

        $xml = $service->buildXml($invoice, [$item], $customer, $company, null);

        $this->assertStringContainsString('CategoryCode>E<', $xml, 'Small-business invoice must use VAT category E (exempt).');
        $this->assertStringContainsString('50.00', $xml, 'Total amount must be present.');
    }
}
