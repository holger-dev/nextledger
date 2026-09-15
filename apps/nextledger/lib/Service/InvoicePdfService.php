<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\CompanyMapper;
use OCA\NextLedger\Db\Customer;
use OCA\NextLedger\Db\CustomerMapper;
use OCA\NextLedger\Db\Invoice;
use OCA\NextLedger\Db\InvoiceItem;
use OCA\NextLedger\Db\InvoiceItemMapper;
use OCA\NextLedger\Db\InvoiceMapper;
use OCA\NextLedger\Db\Offer;
use OCA\NextLedger\Db\OfferMapper;
use OCA\NextLedger\Db\MiscSetting;
use OCA\NextLedger\Db\MiscSettingMapper;
use OCA\NextLedger\Db\TaxSetting;
use OCA\NextLedger\Db\TaxSettingMapper;
use OCA\NextLedger\Db\Texts;
use OCA\NextLedger\Db\TextsMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCA\NextLedger\Service\DocumentLocaleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use RuntimeException;

class InvoicePdfService {
    public function __construct(
        private InvoiceMapper $invoiceMapper,
        private InvoiceItemMapper $invoiceItemMapper,
        private CustomerMapper $customerMapper,
        private CompanyMapper $companyMapper,
        private OfferMapper $offerMapper,
        private TextsMapper $textsMapper,
        private TaxSettingMapper $taxSettingMapper,
        private MiscSettingMapper $miscSettingMapper,
        private ActiveCompanyService $activeCompanyService,
        private DocumentLocaleService $documentLocaleService,
        private ZugferdXmlService $zugferdXmlService,
    ) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function buildPdf(int $invoiceId): array {
        /** @var Invoice $invoice */
        $invoice = $this->invoiceMapper->find($invoiceId);
        $companyId = (int)($invoice->getCompanyId() ?: $this->activeCompanyService->getActiveCompanyId());
        $items = $this->invoiceItemMapper->findByInvoiceId($invoiceId, $companyId);
        $customer = $this->loadCustomer($invoice->getCustomerId(), $companyId);
        $company = $this->loadCompany($companyId);
        $texts = $this->loadTexts($companyId);
        $tax = $this->loadTax($companyId);
        $misc = $this->loadMisc($companyId);
        $offer = $this->loadOffer($invoice->getRelatedOfferId(), $companyId);

        $html = $this->renderHtml($invoice, $items, $customer, $company, $texts, $tax, $misc, $offer);
        $content = $this->renderPdf($html);

        $format = $this->normalizeInvoiceFormat($company?->getInvoiceFormat());
        $isZugferd = false;
        if ($format === ZugferdXmlService::FORMAT_ZUGFERD && $company !== null) {
            try {
                $content = $this->zugferdXmlService->buildZugferdPdf($content, $invoice, $items, $customer, $company, $misc);
                $isZugferd = true;
            } catch (\Throwable $e) {
                // Fall back to a regular PDF if hybrid PDF/A-3 generation fails so the
                // user still gets a usable invoice. The CII XML can still be downloaded
                // as a sidecar file via /api/invoices/{id}/zugferd-xml.
                error_log('NextLedger ZUGFeRD hybrid generation failed: ' . $e->getMessage());
            }
        }

        $languageCode = $this->documentLocaleService->getCompanyLanguage($company);
        $stem = $this->sanitizeFilenamePart($this->documentLocaleService->t($languageCode, 'invoice_filename'));
        $number = $this->sanitizeFilenamePart((string)($invoice->getNumber() ?: $invoiceId));
        $filename = $isZugferd
            ? sprintf('%s-%s-zugferd.pdf', $stem, $number)
            : sprintf('%s-%s.pdf', $stem, $number);

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * Build the EN16931 CII-XML for an invoice as a sidecar download.
     */
    public function buildZugferdXml(int $invoiceId): array {
        /** @var Invoice $invoice */
        $invoice = $this->invoiceMapper->find($invoiceId);
        $companyId = (int)($invoice->getCompanyId() ?: $this->activeCompanyService->getActiveCompanyId());
        $items = $this->invoiceItemMapper->findByInvoiceId($invoiceId, $companyId);
        $customer = $this->loadCustomer($invoice->getCustomerId(), $companyId);
        $company = $this->loadCompany($companyId);
        $misc = $this->loadMisc($companyId);
        if ($company === null) {
            throw new RuntimeException('Aktive Firma nicht gefunden.');
        }
        $xml = $this->zugferdXmlService->buildXml($invoice, $items, $customer, $company, $misc);
        $number = $this->sanitizeFilenamePart((string)($invoice->getNumber() ?: $invoiceId));
        return [
            'filename' => sprintf('Rechnung-%s-zugferd.xml', $number),
            'content' => $xml,
        ];
    }

    private function normalizeInvoiceFormat(?string $value): string {
        $allowed = [ZugferdXmlService::FORMAT_PDF, ZugferdXmlService::FORMAT_ZUGFERD];
        $normalized = strtolower(trim((string)($value ?? '')));
        return in_array($normalized, $allowed, true) ? $normalized : ZugferdXmlService::FORMAT_PDF;
    }

    private function renderPdf(string $html): string {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('PDF-Engine nicht installiert (dompdf).');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        // dompdf's image cache writes into sys_get_temp_dir(), so make sure that
        // sits inside the chroot whitelist.
        $options->set('chroot', [sys_get_temp_dir(), realpath(getcwd())]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param InvoiceItem[] $items
     */
    private function renderHtml(
        Invoice $invoice,
        array $items,
        ?Customer $customer,
        ?Company $company,
        ?Texts $texts,
        ?TaxSetting $tax,
        ?MiscSetting $misc,
        ?Offer $offer,
    ): string {
        $languageCode = $this->documentLocaleService->getCompanyLanguage($company);
        $issueDate = $this->documentLocaleService->formatDate($invoice->getIssueDate(), $languageCode);

        // Issue #19: hide the "due until" line entirely when no due date is set
        // instead of rendering a dash placeholder.
        $dateBlock = sprintf(
            '<p><strong>%s:</strong> %s%s</p>',
            $this->escape($this->t($languageCode, 'date')),
            $issueDate,
            $invoice->getDueDate()
                ? sprintf(
                    '<br><strong>%s:</strong> %s',
                    $this->escape($this->t($languageCode, 'due_until')),
                    $this->documentLocaleService->formatDate($invoice->getDueDate(), $languageCode)
                )
                : ''
        );

        $layout = $this->getDocLayout($company);
        $companyBlock = $this->buildCompanyBlock($company, $layout);

        [$logoSize, $logoBlock, $logoCss] = $this->buildLogoBlock($company);
        $companyHeader = $this->buildCompanyHeader($logoSize, $logoBlock, $companyBlock, $layout);
        $bodyFontPx = $this->resolveFontSize($layout);

        $customerBlock = $customer
            ? sprintf(
                '%s<br>%s %s<br>%s %s<br>%s',
                $this->escape($customer->getCompany()),
                $this->escape($customer->getStreet()),
                $this->escape($customer->getHouseNumber()),
                $this->escape($customer->getZip()),
                $this->escape($customer->getCity()),
                $this->escape($customer->getContactName())
            )
            : '';

        // Issue #24: per-position VAT rates. An item without its own rate inherits
        // the invoice-level rate. When mixed rates are present, the table gains a
        // VAT column and the totals block lists one tax line per rate group.
        $hasItemRates = false;
        foreach ($items as $item) {
            if ($item->getTaxRateBp() !== null) {
                $hasItemRates = true;
                break;
            }
        }
        $defaultRateBp = (int)($invoice->getTaxRateBp() ?? 0);
        $showVatColumn = $hasItemRates && !$invoice->getIsSmallBusiness();

        $rows = '';
        foreach ($items as $item) {
            $itemRateBp = $invoice->getIsSmallBusiness() ? 0 : (int)($item->getTaxRateBp() ?? $defaultRateBp);
            $vatCell = $showVatColumn
                ? sprintf(
                    '<td style="text-align:right">%s%%</td>',
                    $this->documentLocaleService->formatPercent($itemRateBp / 100, $languageCode)
                )
                : '';
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td>%s<td style="text-align:right">%s</td></tr>',
                $this->escape($item->getName()),
                $this->escape($item->getDescription()),
                $this->escape((string)($item->getQuantity() ?? 0)),
                $this->formatMoney($item->getUnitPriceCents(), $company, $languageCode),
                $vatCell,
                $this->formatMoney($item->getTotalCents(), $company, $languageCode)
            );
        }
        $vatHeader = $showVatColumn
            ? sprintf('<th style="text-align:right">%s</th>', $this->escape($this->t($languageCode, 'tax_short')))
            : '';

        // Totals: single line (legacy) or one line per rate group
        if ($invoice->getIsSmallBusiness()) {
            $taxLinesHtml = sprintf(
                '<p>%s</p>',
                $this->escape($tax?->getSmallBusinessNote() ?: $this->t($languageCode, 'small_business'))
            );
            $displayTotal = $invoice->getTotalCents();
        } elseif ($hasItemRates) {
            $groups = $this->buildTaxGroups($items, $defaultRateBp);
            $taxLinesHtml = '';
            $computedTax = 0;
            foreach ($groups as $rateBp => $netCents) {
                $groupTax = (int)round($netCents * $rateBp / 10000);
                $computedTax += $groupTax;
                $taxLinesHtml .= sprintf(
                    '<p>%s (%s%%): %s</p>',
                    $this->escape($this->t($languageCode, 'tax')),
                    $this->documentLocaleService->formatPercent($rateBp / 100, $languageCode),
                    $this->formatMoney($groupTax, $company, $languageCode)
                );
            }
            $displayTotal = (int)($invoice->getSubtotalCents() ?? 0) + $computedTax;
        } else {
            $taxLinesHtml = sprintf(
                '<p>%s (%s%%): %s</p>',
                $this->escape($this->t($languageCode, 'tax')),
                $this->documentLocaleService->formatPercent($defaultRateBp / 100, $languageCode),
                $this->formatMoney($invoice->getTaxCents(), $company, $languageCode)
            );
            $displayTotal = $invoice->getTotalCents();
        }

        $footerText = $invoice->getFooterText() ?? $texts?->getFooterText() ?? '';
        $greeting = $invoice->getGreetingText() ?? $texts?->getInvoiceGreeting() ?? '';
        $extraText = $invoice->getExtraText() ?? '';
        $customFieldBlock = '';
        $customFieldLabel = trim((string)($invoice->getCustomFieldLabel() ?? ''));
        $customFieldValue = trim((string)($invoice->getCustomFieldValue() ?? ''));
        if ($customFieldLabel !== '' && $customFieldValue !== '') {
            $customFieldBlock = sprintf(
                '<p><strong>%s:</strong> %s</p>',
                $this->escape($customFieldLabel),
                $this->escape($customFieldValue)
            );
        }
        $closingText = $texts?->getInvoiceClosingText() ?? '';
        $closingTextBlock = $closingText
            ? sprintf('<p>%s</p>', nl2br($this->escape($closingText)))
            : '';
        // Issue #15: greeting formula and signature name are configurable via
        // the Texte settings; fall back to translation + company owner.
        $greetingFormula = trim((string)($texts?->getClosingGreeting() ?? ''))
            ?: $this->t($languageCode, 'closing_greeting');
        $signatureName = trim((string)($texts?->getSignatureName() ?? ''))
            ?: (string)($company?->getOwnerName() ?? '');
        $closingBlock = $signatureName !== ''
            ? sprintf(
                '<p>%s</p><p>&nbsp;</p><p>%s</p>',
                $this->escape($greetingFormula),
                $this->escape($signatureName)
            )
            : sprintf('<p>%s</p>', $this->escape($greetingFormula));

        $bankParts = [];
        if ($misc?->getBankName()) {
            $bankParts[] = $this->t($languageCode, 'bank') . ': ' . $this->escape($misc->getBankName());
        }
        if ($misc?->getIban()) {
            $bankParts[] = 'IBAN: ' . $this->escape($misc->getIban());
        }
        if ($misc?->getBic()) {
            $bankParts[] = 'BIC: ' . $this->escape($misc->getBic());
        }
        if ($misc?->getAccountHolder()) {
            $bankParts[] = $this->t($languageCode, 'account_holder') . ': ' . $this->escape($misc->getAccountHolder());
        }
        $bankInfo = $bankParts ? sprintf('<p>%s</p>', implode(' | ', $bankParts)) : '';

        $invoiceType = $this->normalizeInvoiceType($invoice->getInvoiceType());
        $title = match ($invoiceType) {
            'advance' => $this->t($languageCode, 'advance_invoice'),
            'final' => $this->t($languageCode, 'final_invoice'),
            default => $this->t($languageCode, 'invoice'),
        };

        $offerReference = '';
        if ($offer) {
            $offerDate = $this->documentLocaleService->formatDate($offer->getIssueDate(), $languageCode);
            $offerNumber = $offer->getNumber() ?: (string)$offer->getId();
            $offerReference = sprintf(
                '<p><strong>%s:</strong> %s %s %s</p>',
                $this->escape($this->t($languageCode, 'offer_reference')),
                $this->escape($offerNumber),
                $this->escape($this->t($languageCode, 'from')),
                $this->escape($offerDate)
            );
        }

        $servicePeriod = '';
        if ($invoiceType === 'advance') {
            $periodStart = $invoice->getServicePeriodStart()
                ? $this->documentLocaleService->formatDate($invoice->getServicePeriodStart(), $languageCode)
                : null;
            $periodEnd = $invoice->getServicePeriodEnd()
                ? $this->documentLocaleService->formatDate($invoice->getServicePeriodEnd(), $languageCode)
                : null;
            if ($periodStart || $periodEnd) {
                $servicePeriod = sprintf(
                    '<p><strong>%s:</strong> %s%s</p>',
                    $this->escape($this->t($languageCode, 'service_period')),
                    $periodStart ? $this->escape($periodStart) : $this->escape($this->t($languageCode, 'dash')),
                    $periodEnd ? ' ' . $this->escape($this->t($languageCode, 'dash')) . ' ' . $this->escape($periodEnd) : ''
                );
            }
        }

        return sprintf(
            '<html><head><meta charset="UTF-8"><style>
                @page { margin: 32px 32px 120px 32px; }
                body { font-family: Helvetica, Arial, sans-serif; font-size: %dpx; color: #1f2933; margin: 0; }
                .header { width: 100%%; border-collapse: collapse; }
                .header td { vertical-align: top; padding: 0; }
                .company { text-align: right; font-size: 13px; line-height: 1.4; }
                .customer { margin-top: 18px; font-size: 13px; line-height: 1.4; }
                h1 { font-size: 20px; margin: 24px 0 8px; }
                .logo-small { max-height: 32px; max-width: 220px; }
                .logo-medium { max-height: 64px; max-width: 260px; }
                .logo-large { max-height: 110px; max-width: 100%%; display: block; }
                .logo-banner { width: 100%%; text-align: left; margin-bottom: 14px; }
                %s
                table.items { width: 100%%; border-collapse: collapse; margin-top: 12px; }
                table.items th, table.items td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; vertical-align: top; }
                table.items th { text-align: left; background: #f3f4f6; }
                .totals { margin-top: 12px; text-align: right; }
                .footer { position: fixed; left: 0; right: 0; bottom: -94px; font-size: 10px; color: #4b5563; border-top: 1px solid #d1d5db; padding-top: 8px; line-height: 1.35; text-align: center; }
                .footer p { margin: 0 0 4px; }
            </style></head><body>
            <div class="footer">
              <p>%s</p>
              %s
              %s
            </div>
            %s
            <div class="customer">%s</div>
            <h1>%s %s</h1>
            <p><strong>%s:</strong> %s</p>
            %s
            %s
            %s
            %s
            <p>%s</p>
            <p>%s</p>
            <table class="items">
              <thead>
                <tr>
                  <th>%s</th>
                  <th>%s</th>
                  <th style="text-align:right">%s</th>
                  <th style="text-align:right">%s</th>
                  %s
                  <th style="text-align:right">%s</th>
                </tr>
              </thead>
              <tbody>%s</tbody>
            </table>
            <div class="totals">
              <p>%s: %s</p>
              %s
              <p><strong>%s: %s</strong></p>
            </div>
            %s
            %s
            </body></html>',
            $bodyFontPx,
            $logoCss,
            nl2br($this->escape($footerText)),
            $bankInfo,
            '',
            $companyHeader,
            $customerBlock,
            $this->escape($title),
            $this->escape($invoice->getNumber() ?? ''),
            $this->escape($this->t($languageCode, 'invoice_number')),
            $this->escape($invoice->getNumber() ?? ''),
            $dateBlock,
            $offerReference,
            $servicePeriod,
            $customFieldBlock,
            nl2br($this->escape($greeting)),
            nl2br($this->escape($extraText)),
            $this->escape($this->t($languageCode, 'position')),
            $this->escape($this->t($languageCode, 'description')),
            $this->escape($this->t($languageCode, 'quantity')),
            $this->escape($this->t($languageCode, 'unit_price')),
            $vatHeader,
            $this->escape($this->t($languageCode, 'total')),
            $rows,
            $this->escape($this->t($languageCode, 'subtotal')),
            $this->formatMoney($invoice->getSubtotalCents(), $company, $languageCode),
            $taxLinesHtml,
            $this->escape($this->t($languageCode, 'total')),
            $this->formatMoney($displayTotal, $company, $languageCode),
            $closingTextBlock,
            $closingBlock
        );
    }

    /**
     * Group net amounts (cents) by effective tax rate (basis points).
     *
     * @param InvoiceItem[] $items
     * @return array<int, int> rateBp => summed net cents
     */
    private function buildTaxGroups(array $items, int $defaultRateBp): array {
        $groups = [];
        foreach ($items as $item) {
            $rateBp = (int)($item->getTaxRateBp() ?? $defaultRateBp);
            $groups[$rateBp] = ($groups[$rateBp] ?? 0) + (int)($item->getTotalCents() ?? 0);
        }
        krsort($groups);
        return $groups;
    }

    private function normalizeInvoiceType(?string $invoiceType): string {
        return match (strtolower((string)$invoiceType)) {
            'advance', 'abschlag' => 'advance',
            'final', 'schluss' => 'final',
            default => 'standard',
        };
    }

    private function escape(?string $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function t(string $languageCode, string $key): string {
        return $this->documentLocaleService->t($languageCode, $key);
    }

    private function formatMoney(?int $cents, ?Company $company, string $languageCode): string {
        return $this->documentLocaleService->formatMoney($cents, $company?->getCurrencyCode(), $languageCode);
    }

    private function sanitizeFilenamePart(string $value): string {
        $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '_', trim($value)) ?: 'document';
        return trim($clean, '._-') ?: 'document';
    }

    private function loadCustomer(?int $customerId, int $companyId): ?Customer {
        if (!$customerId) {
            return null;
        }
        try {
            /** @var Customer $customer */
            $customer = $this->customerMapper->findByIdAndCompanyId($customerId, $companyId);
            return $customer;
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return null;
        }
    }

    private function loadCompany(int $companyId): ?Company {
        try {
            /** @var Company $company */
            $company = $this->companyMapper->find($companyId);
            return $company;
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return null;
        }
    }

    private function loadOffer(?int $offerId, int $companyId): ?Offer {
        if (!$offerId) {
            return null;
        }
        try {
            /** @var Offer $offer */
            $offer = $this->offerMapper->findByIdAndCompanyId($offerId, $companyId);
            return $offer;
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return null;
        }
    }

    private function loadTexts(int $companyId): ?Texts {
        $items = $this->textsMapper->findAllByCompanyId($companyId, 1, 0);
        return $items[0] ?? null;
    }

    private function loadTax(int $companyId): ?TaxSetting {
        $items = $this->taxSettingMapper->findAllByCompanyId($companyId, 1, 0);
        return $items[0] ?? null;
    }

    private function loadMisc(int $companyId): ?MiscSetting {
        $items = $this->miscSettingMapper->findAllByCompanyId($companyId, 1, 0);
        return $items[0] ?? null;
    }

    /**
     * @return array{0: string, 1: string, 2: string} [size, html, extraCss]
     */
    private function buildLogoBlock(?Company $company): array {
        $size = $this->normalizeLogoSize($company?->getLogoSize());
        $data = trim((string)($company?->getLogoData() ?? ''));
        $mime = trim((string)($company?->getLogoMime() ?? ''));
        if ($data === '' || $mime === '' || !str_starts_with($mime, 'image/')) {
            return [$size, '', ''];
        }
        // Decode the base64 once, write the raw bytes to a tempfile, and reference
        // it by absolute path. dompdf's HTML parsers (both libxml and html5lib)
        // can corrupt long base64 attribute values, which then makes GD fail with
        // "IDAT: incorrect data check". Going via the filesystem avoids that.
        $bytes = base64_decode($data, true);
        if ($bytes === false || $bytes === '') {
            return [$size, '', ''];
        }
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
        $companyId = (int)($company->getId() ?? 0);
        $hash = substr(md5($data), 0, 10);
        $path = sprintf('%s/nextledger-logo-%d-%s.%s', sys_get_temp_dir(), $companyId, $hash, $ext);
        if (!file_exists($path) || filesize($path) !== strlen($bytes)) {
            @file_put_contents($path, $bytes);
        }
        if (!file_exists($path)) {
            return [$size, '', ''];
        }
        $heightPx = match ($size) {
            'small' => 32,
            'large' => 110,
            default => 64,
        };
        $cssClass = match ($size) {
            'small' => 'logo-small',
            'large' => 'logo-large',
            default => 'logo-medium',
        };
        $html = sprintf(
            '<img class="%s" height="%d" src="%s" alt="logo">',
            $cssClass,
            $heightPx,
            $this->escape($path)
        );
        return [$size, $html, ''];
    }

    /**
     * Issues #18/#20: parse the per-company document layout JSON with defaults
     * matching the pre-1.7.0 output.
     *
     * @return array{showVatId: bool, showTaxId: bool, showPhone: bool, showEmail: bool, companyBlockPosition: string, fontSize: string}
     */
    private function getDocLayout(?Company $company): array {
        $defaults = [
            'showVatId' => false,
            'showTaxId' => false,
            'showPhone' => false,
            'showEmail' => true,
            'companyBlockPosition' => 'right',
            'fontSize' => 'normal',
        ];
        $raw = trim((string)($company?->getDocLayout() ?? ''));
        if ($raw === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        $layout = array_merge($defaults, array_intersect_key($decoded, $defaults));
        $layout['companyBlockPosition'] = in_array($layout['companyBlockPosition'], ['left', 'right'], true)
            ? $layout['companyBlockPosition'] : 'right';
        $layout['fontSize'] = in_array($layout['fontSize'], ['small', 'normal', 'large'], true)
            ? $layout['fontSize'] : 'normal';
        foreach (['showVatId', 'showTaxId', 'showPhone', 'showEmail'] as $flag) {
            $layout[$flag] = (bool)$layout[$flag];
        }
        return $layout;
    }

    private function resolveFontSize(array $layout): int {
        return match ($layout['fontSize']) {
            'small' => 11,
            'large' => 13,
            default => 12,
        };
    }

    private function buildCompanyBlock(?Company $company, array $layout): string {
        if ($company === null) {
            return '';
        }
        $lines = [
            $this->escape($company->getName()),
            trim($this->escape($company->getStreet()) . ' ' . $this->escape($company->getHouseNumber())),
            trim($this->escape($company->getZip()) . ' ' . $this->escape($company->getCity())),
        ];
        if ($layout['showEmail'] && $company->getEmail()) {
            $lines[] = $this->escape($company->getEmail());
        }
        if ($layout['showPhone'] && $company->getPhone()) {
            $lines[] = $this->escape($company->getPhone());
        }
        if ($layout['showVatId'] && $company->getVatId()) {
            $lines[] = 'USt-IdNr.: ' . $this->escape($company->getVatId());
        }
        if ($layout['showTaxId'] && $company->getTaxId()) {
            $lines[] = 'St.-Nr.: ' . $this->escape($company->getTaxId());
        }
        return implode('<br>', array_filter($lines, static fn(string $line): bool => $line !== ''));
    }

    private function buildCompanyHeader(string $size, string $logoHtml, string $companyBlock, array $layout = []): string {
        $position = $layout['companyBlockPosition'] ?? 'right';
        $align = $position === 'left' ? 'left' : 'right';
        $companyDiv = sprintf('<div class="company" style="text-align:%s">%s</div>', $align, $companyBlock);
        if ($logoHtml === '') {
            return $companyDiv;
        }
        if ($size === 'large') {
            return sprintf('<div class="logo-banner">%s</div>%s', $logoHtml, $companyDiv);
        }
        $logoWidth = $size === 'small' ? 55 : 45;
        $companyWidth = 100 - $logoWidth;
        // company block left → logo moves to the right column
        if ($position === 'left') {
            return sprintf(
                '<table class="header"><tr>'
                . '<td class="company" style="width:%d%%; text-align:left">%s</td>'
                . '<td style="width:%d%%; text-align:right">%s</td>'
                . '</tr></table>',
                $companyWidth,
                $companyBlock,
                $logoWidth,
                $logoHtml
            );
        }
        return sprintf(
            '<table class="header"><tr>'
            . '<td style="width:%d%%; text-align:left">%s</td>'
            . '<td class="company" style="width:%d%%; text-align:right">%s</td>'
            . '</tr></table>',
            $logoWidth,
            $logoHtml,
            $companyWidth,
            $companyBlock
        );
    }

    private function normalizeLogoSize(?string $value): string {
        $allowed = ['small', 'medium', 'large'];
        $normalized = strtolower(trim((string)($value ?? '')));
        return in_array($normalized, $allowed, true) ? $normalized : 'medium';
    }

}
