<?php

declare(strict_types=1);

namespace OCA\NextLedger\Tests\Unit\Service;

use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\Customer;
use PHPUnit\Framework\TestCase;

/**
 * Sanity tests for the new 1.6.3 entity fields. These guard the migration
 * + entity additions against accidental removal in future refactors.
 */
class CompanyEntityTest extends TestCase {
    public function testCompanyExposesLogoFields(): void {
        $company = new Company();
        $company->setLogoData('aGVsbG8=');
        $company->setLogoMime('image/png');
        $company->setLogoSize('large');
        $this->assertSame('aGVsbG8=', $company->getLogoData());
        $this->assertSame('image/png', $company->getLogoMime());
        $this->assertSame('large', $company->getLogoSize());
    }

    public function testCompanyExposesInvoiceFormatAndCountry(): void {
        $company = new Company();
        $company->setInvoiceFormat('zugferd');
        $company->setCountryCode('AT');
        $this->assertSame('zugferd', $company->getInvoiceFormat());
        $this->assertSame('AT', $company->getCountryCode());
    }

    public function testCustomerExposesCountryAndVat(): void {
        $customer = new Customer();
        $customer->setCountryCode('CH');
        $customer->setVatId('CHE-123.456.789');
        $this->assertSame('CH', $customer->getCountryCode());
        $this->assertSame('CHE-123.456.789', $customer->getVatId());
    }
}
