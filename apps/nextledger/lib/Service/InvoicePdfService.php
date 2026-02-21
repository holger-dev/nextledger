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
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IConfig;
use OCP\IUserSession;
use DateTimeImmutable;
use DateTimeZone;
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
        private IConfig $config,
        private IUserSession $userSession,
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
        $filename = sprintf('rechnung-%s.pdf', $invoice->getNumber() ?: $invoiceId);

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    private function renderPdf(string $html): string {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('PDF-Engine nicht installiert (dompdf).');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

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
        $issueDate = $this->formatDate($invoice->getIssueDate());
        $dueDate = $this->formatDate($invoice->getDueDate());

        $companyBlock = $company
            ? sprintf(
                '%s<br>%s %s<br>%s %s<br>%s',
                $this->escape($company->getName()),
                $this->escape($company->getStreet()),
                $this->escape($company->getHouseNumber()),
                $this->escape($company->getZip()),
                $this->escape($company->getCity()),
                $this->escape($company->getEmail())
            )
            : '';

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

        $rows = '';
        foreach ($items as $item) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td></tr>',
                $this->escape($item->getName()),
                $this->escape($item->getDescription()),
                $this->escape((string)($item->getQuantity() ?? 0)),
                $this->formatMoney($item->getUnitPriceCents()),
                $this->formatMoney($item->getTotalCents())
            );
        }

        $taxLine = $invoice->getIsSmallBusiness()
            ? ($tax?->getSmallBusinessNote() ?: 'Kleinunternehmerregelung')
            : sprintf('Steuer (%s%%)', number_format(($invoice->getTaxRateBp() ?? 0) / 100, 2, ',', '.'));
        $taxAmount = $invoice->getIsSmallBusiness() ? '' : $this->formatMoney($invoice->getTaxCents());

        $footerText = $texts?->getFooterText() ?? '';
        $greeting = $invoice->getGreetingText() ?? $texts?->getInvoiceGreeting() ?? '';
        $extraText = $invoice->getExtraText() ?? '';
        $closingText = $texts?->getInvoiceClosingText() ?? '';
        $ownerName = $company?->getOwnerName();
        $closingTextBlock = $closingText
            ? sprintf('<p>%s</p>', nl2br($this->escape($closingText)))
            : '';
        $closingBlock = $ownerName
            ? sprintf(
                '<p>Mit freundlichen Grüßen</p><p>&nbsp;</p><p>%s</p>',
                $this->escape($ownerName)
            )
            : '<p>Mit freundlichen Grüßen</p>';

        $bankParts = [];
        if ($misc?->getBankName()) {
            $bankParts[] = 'Bank: ' . $this->escape($misc->getBankName());
        }
        if ($misc?->getIban()) {
            $bankParts[] = 'IBAN: ' . $this->escape($misc->getIban());
        }
        if ($misc?->getBic()) {
            $bankParts[] = 'BIC: ' . $this->escape($misc->getBic());
        }
        if ($misc?->getAccountHolder()) {
            $bankParts[] = 'Kontoinhaber: ' . $this->escape($misc->getAccountHolder());
        }
        $bankInfo = $bankParts ? sprintf('<p>%s</p>', implode(' | ', $bankParts)) : '';

        $invoiceType = $this->normalizeInvoiceType($invoice->getInvoiceType());
        $title = match ($invoiceType) {
            'advance' => 'Abschlagsrechnung',
            'final' => 'Schlussrechnung',
            default => 'Rechnung',
        };

        $offerReference = '';
        if ($offer) {
            $offerDate = $this->formatDate($offer->getIssueDate());
            $offerNumber = $offer->getNumber() ?: (string)$offer->getId();
            $offerReference = sprintf(
                '<p><strong>Angebot:</strong> %s vom %s</p>',
                $this->escape($offerNumber),
                $this->escape($offerDate)
            );
        }

        $servicePeriod = '';
        if ($invoiceType === 'advance') {
            $periodStart = $invoice->getServicePeriodStart()
                ? $this->formatDate($invoice->getServicePeriodStart())
                : null;
            $periodEnd = $invoice->getServicePeriodEnd()
                ? $this->formatDate($invoice->getServicePeriodEnd())
                : null;
            if ($periodStart || $periodEnd) {
                $servicePeriod = sprintf(
                    '<p><strong>Leistungszeitraum:</strong> %s%s</p>',
                    $periodStart ? $this->escape($periodStart) : '–',
                    $periodEnd ? ' – ' . $this->escape($periodEnd) : ''
                );
            }
        }

        return sprintf(
            '<html><head><meta charset="UTF-8"><style>
                @page { margin: 32px 32px 110px 32px; }
                body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2933; margin: 0; padding-bottom: 90px; }
                .company { text-align: right; font-size: 13px; line-height: 1.4; }
                .customer { margin-top: 18px; font-size: 13px; line-height: 1.4; }
                h1 { font-size: 20px; margin: 24px 0 8px; }
                table { width: 100%%; border-collapse: collapse; margin-top: 12px; }
                th, td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; vertical-align: top; }
                th { text-align: left; background: #f3f4f6; }
                .totals { margin-top: 12px; text-align: right; }
                .footer { position: fixed; left: 0; right: 0; bottom: -80px; font-size: 11px; color: #4b5563; text-align: center; }
            </style></head><body>
            <div class="company">%s</div>
            <div class="customer">%s</div>
            <h1>%s %s</h1>
            <p><strong>Rechnungsnummer:</strong> %s</p>
            <p><strong>Datum:</strong> %s<br><strong>Fällig bis:</strong> %s</p>
            %s
            %s
            <p>%s</p>
            <p>%s</p>
            <table>
              <thead>
                <tr>
                  <th>Position</th>
                  <th>Beschreibung</th>
                  <th style="text-align:right">Menge</th>
                  <th style="text-align:right">Einzelpreis</th>
                  <th style="text-align:right">Gesamt</th>
                </tr>
              </thead>
              <tbody>%s</tbody>
            </table>
            <div class="totals">
              <p>Zwischensumme: %s</p>
              <p>%s%s</p>
              <p><strong>Gesamt: %s</strong></p>
            </div>
            %s
            %s
            <div class="footer">
              <p>%s</p>
              %s
              %s
            </div>
            </body></html>',
            $companyBlock,
            $customerBlock,
            $this->escape($title),
            $this->escape($invoice->getNumber() ?? ''),
            $this->escape($invoice->getNumber() ?? ''),
            $issueDate,
            $dueDate,
            $offerReference,
            $servicePeriod,
            nl2br($this->escape($greeting)),
            nl2br($this->escape($extraText)),
            $rows,
            $this->formatMoney($invoice->getSubtotalCents()),
            $this->escape($taxLine),
            $taxAmount ? ': ' . $taxAmount : '',
            $this->formatMoney($invoice->getTotalCents()),
            $closingTextBlock,
            $closingBlock,
            nl2br($this->escape($footerText)),
            $bankInfo,
            ''
        );
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

    private function formatMoney(?int $cents): string {
        if ($cents === null) {
            return '–';
        }
        return number_format($cents / 100, 2, ',', '.') . ' €';
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

    private function formatDate(?int $value): string {
        if (!$value) {
            return '–';
        }

        $timezone = $this->getUserTimezone();
        try {
            $date = (new DateTimeImmutable('@' . $value))->setTimezone(new DateTimeZone($timezone));
        } catch (\Throwable $e) {
            $date = (new DateTimeImmutable('@' . $value))->setTimezone(new DateTimeZone('UTC'));
        }

        return $date->format('d.m.Y');
    }

    private function getUserTimezone(): string {
        $user = $this->userSession->getUser();
        if ($user && method_exists($user, 'getUID')) {
            $timezone = (string)$this->config->getUserValue($user->getUID(), 'core', 'timezone', 'UTC');
            if ($timezone !== '') {
                return $timezone;
            }
        }

        return 'UTC';
    }
}
