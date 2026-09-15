<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdPaymentMeans;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdSettings;
use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\Customer;
use OCA\NextLedger\Db\Invoice;
use OCA\NextLedger\Db\InvoiceItem;
use OCA\NextLedger\Db\MiscSetting;
use RuntimeException;

/**
 * Builds a ZUGFeRD EN16931 (Cross Industry Invoice) XML document from a NextLedger
 * invoice and embeds it into an existing PDF/A-3 container.
 *
 * Profile: EN16931 (Comfort) — strict enough for nearly all German B2B exchange
 * scenarios while still allowing a normal-looking visual representation.
 */
class ZugferdXmlService {
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_ZUGFERD = 'zugferd';

    /**
     * Build the CII-XML for a single invoice. Returns the raw XML string.
     *
     * @param InvoiceItem[] $items
     */
    public function buildXml(
        Invoice $invoice,
        array $items,
        ?Customer $customer,
        Company $company,
        ?MiscSetting $misc,
    ): string {
        return $this->withDefaultEntityLoader(
            fn(): string => $this->createDocumentBuilder($invoice, $items, $customer, $company, $misc)->getContent()
        );
    }

    /**
     * Build a ZUGFeRD document builder pre-populated with all the invoice data,
     * so that the PdfBuilder can take both XML + the source PDF and produce
     * a PDF/A-3 with the CII-XML embedded.
     *
     * @param InvoiceItem[] $items
     */
    private function createDocumentBuilder(
        Invoice $invoice,
        array $items,
        ?Customer $customer,
        Company $company,
        ?MiscSetting $misc,
    ): ZugferdDocumentBuilder {
        if (!class_exists(ZugferdDocumentBuilder::class)) {
            throw new RuntimeException(
                'horstoeko/zugferd ist nicht installiert. Bitte "composer install" im Verzeichnis apps/nextledger ausführen.'
            );
        }

        $document = ZugferdDocumentBuilder::CreateNew(ZugferdProfiles::PROFILE_EN16931);
        $document
            ->setDocumentInformation(
                (string)($invoice->getNumber() ?: $invoice->getId()),
                ZugferdInvoiceType::INVOICE,
                $this->date($invoice->getIssueDate()),
                $this->normalizeCurrency($company->getCurrencyCode())
            );

        $sellerName = (string)($company->getName() ?: 'Seller');
        $sellerCountry = $this->normalizeCountry($company->getCountryCode(), 'DE');
        $document
            ->setDocumentSeller($sellerName)
            ->setDocumentSellerAddress(
                trim(((string)$company->getStreet()) . ' ' . ((string)$company->getHouseNumber())),
                '',
                '',
                (string)($company->getZip() ?: ''),
                (string)($company->getCity() ?: ''),
                $sellerCountry
            );

        $sellerVat = trim((string)($company->getVatId() ?? ''));
        if ($sellerVat !== '') {
            $document->addDocumentSellerTaxRegistration('VA', $sellerVat);
        }
        $sellerTax = trim((string)($company->getTaxId() ?? ''));
        if ($sellerTax !== '') {
            $document->addDocumentSellerTaxRegistration('FC', $sellerTax);
        }
        if ($company->getEmail()) {
            $document->setDocumentSellerCommunication('EM', (string)$company->getEmail());
        }

        $buyerName = (string)($customer?->getCompany() ?: ($customer?->getContactName() ?: 'Buyer'));
        $buyerCountry = $this->normalizeCountry($customer?->getCountryCode(), $sellerCountry);
        $document
            ->setDocumentBuyer($buyerName)
            ->setDocumentBuyerAddress(
                trim(((string)($customer?->getStreet() ?? '')) . ' ' . ((string)($customer?->getHouseNumber() ?? ''))),
                '',
                '',
                (string)($customer?->getZip() ?? ''),
                (string)($customer?->getCity() ?? ''),
                $buyerCountry
            );
        $buyerVat = trim((string)($customer?->getVatId() ?? ''));
        if ($buyerVat !== '') {
            $document->addDocumentBuyerTaxRegistration('VA', $buyerVat);
        }
        if ($customer?->getEmail()) {
            $document->setDocumentBuyerCommunication('EM', (string)$customer->getEmail());
        }

        if ($invoice->getDueDate()) {
            $document->addDocumentPaymentTerm('', $this->date($invoice->getDueDate()));
        }

        if ($misc && trim((string)$misc->getIban()) !== '') {
            $document->addDocumentPaymentMean(
                ZugferdPaymentMeans::UNTDID_4461_58,
                null,
                null,
                null,
                null,
                trim((string)$misc->getIban()),
                null,
                trim((string)($misc->getAccountHolder() ?? '')),
                trim((string)($misc->getBic() ?? ''))
            );
        }

        // ---- Positions (issue #24: per-position VAT rates)
        // Use literal codes from EN16931 / UNTDID 5305 — they are stable across
        // horstoeko/zugferd releases (the constant names have been renamed once).
        $defaultRateBp = $invoice->getIsSmallBusiness() ? 0 : (int)($invoice->getTaxRateBp() ?? 0);
        $hasItemRates = false;
        $line = 1;
        foreach ($items as $item) {
            $itemRateBp = $invoice->getIsSmallBusiness()
                ? 0
                : (int)($item->getTaxRateBp() ?? $defaultRateBp);
            if ($item->getTaxRateBp() !== null) {
                $hasItemRates = true;
            }
            $itemCategory = $invoice->getIsSmallBusiness() ? 'E' : ($itemRateBp > 0 ? 'S' : 'Z');
            $document
                ->addNewPosition((string)$line)
                ->setDocumentPositionProductDetails((string)($item->getName() ?: 'Position'), (string)($item->getDescription() ?? ''))
                ->setDocumentPositionGrossPrice($this->cents($item->getUnitPriceCents()))
                ->setDocumentPositionNetPrice($this->cents($item->getUnitPriceCents()))
                ->setDocumentPositionQuantity((float)($item->getQuantity() ?? 0), ZugferdUnitCodes::REC20_PIECE)
                ->addDocumentPositionTax($itemCategory, 'VAT', round($itemRateBp / 100, 2))
                ->setDocumentPositionLineSummation($this->cents($item->getTotalCents()));
            $line++;
        }

        // ---- Tax + totals: one BG-23 group per distinct rate
        $subtotal = $this->cents($invoice->getSubtotalCents());
        if ($invoice->getIsSmallBusiness()) {
            $document->addDocumentTax('E', 'VAT', $subtotal, 0.0, 0.0);
            $taxAmount = 0.0;
        } elseif ($hasItemRates) {
            $groups = [];
            foreach ($items as $item) {
                $rateBp = (int)($item->getTaxRateBp() ?? $defaultRateBp);
                $groups[$rateBp] = ($groups[$rateBp] ?? 0) + (int)($item->getTotalCents() ?? 0);
            }
            krsort($groups);
            $taxAmount = 0.0;
            foreach ($groups as $rateBp => $netCents) {
                $groupNet = round($netCents / 100, 2);
                $groupTax = round($netCents * $rateBp / 10000) / 100;
                $taxAmount += $groupTax;
                $document->addDocumentTax(
                    $rateBp > 0 ? 'S' : 'Z',
                    'VAT',
                    $groupNet,
                    $groupTax,
                    round($rateBp / 100, 2)
                );
            }
            $taxAmount = round($taxAmount, 2);
        } else {
            $taxAmount = $this->cents($invoice->getTaxCents());
            $document->addDocumentTax(
                $defaultRateBp > 0 ? 'S' : 'Z',
                'VAT',
                $subtotal,
                $taxAmount,
                round($defaultRateBp / 100, 2)
            );
        }

        $total = round($subtotal + $taxAmount, 2);
        $document->setDocumentSummation(
            $total,
            $total,
            $subtotal,
            0.0,
            0.0,
            $subtotal,
            $taxAmount,
            null,
            0.0
        );

        return $document;
    }

    /**
     * Render an EN16931-conformant ZUGFeRD PDF/A-3 from invoice data + a previously
     * dompdf-rendered visual PDF.
     *
     * @param InvoiceItem[] $items
     */
    public function buildZugferdPdf(
        string $sourcePdf,
        Invoice $invoice,
        array $items,
        ?Customer $customer,
        Company $company,
        ?MiscSetting $misc,
    ): string {
        if (!class_exists(ZugferdDocumentPdfBuilder::class)) {
            throw new RuntimeException(
                'horstoeko/zugferd ist nicht installiert. Bitte "composer install" im Verzeichnis apps/nextledger ausführen.'
            );
        }

        return $this->withDefaultEntityLoader(function () use ($sourcePdf, $invoice, $items, $customer, $company, $misc): string {
            $this->ensureXmpReadable();
            $document = $this->createDocumentBuilder($invoice, $items, $customer, $company, $misc);
            $pdfBuilder = new ZugferdDocumentPdfBuilder($document, $sourcePdf);
            $pdfBuilder->generateDocument();
            $output = $pdfBuilder->downloadString();
            if (!is_string($output) || $output === '') {
                throw new RuntimeException('PDF/A-3 Erzeugung lieferte leeres Ergebnis.');
            }
            return $output;
        });
    }

    /**
     * Nextcloud installs a libxml external entity loader that always returns null
     * (XXE hardening). horstoeko/zugferd loads its own local XMP/XSD assets via
     * simplexml_load_file(), which that loader silently breaks:
     * "Failed to load external entity because the resolver function returned null".
     *
     * We temporarily restore libxml's default loader for the duration of the
     * ZUGFeRD calls and always reinstate a null-returning loader afterwards.
     * Only local vendor assets are loaded in this window; no user-controlled XML
     * is parsed here, so the XXE surface stays closed.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withDefaultEntityLoader(callable $callback) {
        // Restore libxml's built-in resolver (passing null resets to default).
        libxml_set_external_entity_loader(null);
        try {
            return $callback();
        } finally {
            // Re-arm Nextcloud's hardening: block all external entity loading again.
            libxml_set_external_entity_loader(static fn() => null);
        }
    }

    private function date(?int $timestamp): \DateTime {
        return (new \DateTime())->setTimestamp((int)($timestamp ?: time()));
    }

    private function cents(?int $cents): float {
        return round(((int)$cents) / 100, 2);
    }

    private function taxRatePercent(Invoice $invoice): float {
        if ($invoice->getIsSmallBusiness()) {
            return 0.0;
        }
        $bp = (int)($invoice->getTaxRateBp() ?? 0);
        return round($bp / 100, 2);
    }

    private function normalizeCurrency(?string $code): string {
        $c = strtoupper(trim((string)($code ?? '')));
        return $c !== '' ? $c : 'EUR';
    }

    private function normalizeCountry(?string $code, string $fallback): string {
        $c = strtoupper(trim((string)($code ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $c)) {
            return $c;
        }
        return $fallback;
    }

    /**
     * The horstoeko/zugferd library reads its XMP template via simplexml_load_file().
     * If composer installs vendor/ on the host with restrictive permissions (mode 600),
     * the Apache user inside the container can't read the asset. As a workaround we
     * make sure a world-readable copy lives in a temp directory and point the library
     * there, but only if the original path is not readable.
     */
    /**
     * The horstoeko/zugferd library reads its XMP template via simplexml_load_file().
     * If the vendor/ directory ends up with restrictive permissions (e.g. composer
     * install on the host with a different uid than the web server, or shared hosting
     * with mode 0600), the load fails silently and the PdfBuilder crashes. As a
     * defensive measure we read the asset ourselves and, if necessary, drop a
     * world-readable copy in the temp directory before pointing the library there.
     */
    private function ensureXmpReadable(): void {
        if (!class_exists(ZugferdSettings::class)) {
            return;
        }
        $original = ZugferdSettings::getFullXmpMetaDataFilename();
        if (is_readable($original) && @simplexml_load_file($original) !== false) {
            return;
        }
        $contents = @file_get_contents($original);
        if ($contents === false || $contents === '') {
            return;
        }
        $tempDir = sys_get_temp_dir() . '/nextledger-zugferd-assets';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0o755, true);
        }
        $tempFile = $tempDir . '/facturx_extension_schema.xmp';
        if (!file_exists($tempFile) || filesize($tempFile) !== strlen($contents)) {
            @file_put_contents($tempFile, $contents);
            @chmod($tempFile, 0o644);
        }
        if (is_readable($tempFile)) {
            ZugferdSettings::setXmpMetaDataFilename($tempFile);
        }
    }
}
