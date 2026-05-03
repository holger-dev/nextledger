<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\CompanyMapper;
use OCA\NextLedger\Db\Customer;
use OCA\NextLedger\Db\CustomerMapper;
use OCA\NextLedger\Db\MiscSetting;
use OCA\NextLedger\Db\MiscSettingMapper;
use OCA\NextLedger\Db\Offer;
use OCA\NextLedger\Db\OfferItem;
use OCA\NextLedger\Db\OfferItemMapper;
use OCA\NextLedger\Db\OfferMapper;
use OCA\NextLedger\Db\TaxSetting;
use OCA\NextLedger\Db\TaxSettingMapper;
use OCA\NextLedger\Db\Texts;
use OCA\NextLedger\Db\TextsMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCA\NextLedger\Service\DocumentLocaleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use RuntimeException;
use Dompdf\Dompdf;
use Dompdf\Options;

class OfferPdfService {
    public function __construct(
        private OfferMapper $offerMapper,
        private OfferItemMapper $offerItemMapper,
        private CustomerMapper $customerMapper,
        private CompanyMapper $companyMapper,
        private TextsMapper $textsMapper,
        private TaxSettingMapper $taxSettingMapper,
        private MiscSettingMapper $miscSettingMapper,
        private ActiveCompanyService $activeCompanyService,
        private DocumentLocaleService $documentLocaleService,
    ) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function buildPdf(int $offerId): array {
        /** @var Offer $offer */
        $offer = $this->offerMapper->find($offerId);
        $companyId = (int)($offer->getCompanyId() ?: $this->activeCompanyService->getActiveCompanyId());
        $items = $this->offerItemMapper->findByOfferId($offerId, $companyId);
        $customer = $this->loadCustomer($offer->getCustomerId(), $companyId);
        $company = $this->loadCompany($companyId);
        $texts = $this->loadTexts($companyId);
        $tax = $this->loadTax($companyId);
        $misc = $this->loadMisc($companyId);

        $html = $this->renderHtml($offer, $items, $customer, $company, $texts, $tax, $misc);
        $content = $this->renderPdf($html);
        $languageCode = $this->documentLocaleService->getCompanyLanguage($company);
        $filename = sprintf(
            '%s-%s.pdf',
            $this->sanitizeFilenamePart($this->documentLocaleService->t($languageCode, 'offer_filename')),
            $this->sanitizeFilenamePart((string)($offer->getNumber() ?: $offerId))
        );

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
     * @param OfferItem[] $items
     */
    private function renderHtml(
        Offer $offer,
        array $items,
        ?Customer $customer,
        ?Company $company,
        ?Texts $texts,
        ?TaxSetting $tax,
        ?MiscSetting $misc,
    ): string {
        $languageCode = $this->documentLocaleService->getCompanyLanguage($company);
        $issueDate = $this->documentLocaleService->formatDate($offer->getIssueDate(), $languageCode);
        $validUntil = $this->documentLocaleService->formatDate($offer->getValidUntil(), $languageCode);

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
                $this->formatMoney($item->getUnitPriceCents(), $company, $languageCode),
                $this->formatMoney($item->getTotalCents(), $company, $languageCode)
            );
        }

        $taxLine = $offer->getIsSmallBusiness()
            ? ($tax?->getSmallBusinessNote() ?: $this->t($languageCode, 'small_business'))
            : sprintf('%s (%s%%)', $this->t($languageCode, 'tax'), $this->documentLocaleService->formatPercent(($offer->getTaxRateBp() ?? 0) / 100, $languageCode));
        $taxAmount = $offer->getIsSmallBusiness() ? '' : $this->formatMoney($offer->getTaxCents(), $company, $languageCode);

        $footerText = $texts?->getFooterText() ?? '';
        $greeting = $offer->getGreetingText() ?? $texts?->getOfferGreeting() ?? '';
        $extraText = $offer->getExtraText() ?? '';
        $closingText = $texts?->getOfferClosingText() ?? '';
        $ownerName = $company?->getOwnerName();
        $closingTextBlock = $closingText
            ? sprintf('<p>%s</p>', nl2br($this->escape($closingText)))
            : '';
        $closingBlock = $ownerName
            ? sprintf(
                '<p>%s</p><p>&nbsp;</p><p>%s</p>',
                $this->escape($this->t($languageCode, 'closing_greeting')),
                $this->escape($ownerName)
            )
            : sprintf('<p>%s</p>', $this->escape($this->t($languageCode, 'closing_greeting')));

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
                .footer { position: fixed; left: 0; right: 0; bottom: -80px; font-size: 11px; color: #4b5563; }
            </style></head><body>
            <div class="company">%s</div>
            <div class="customer">%s</div>
            <h1>%s %s</h1>
            <p><strong>%s:</strong> %s</p>
            <p><strong>%s:</strong> %s<br><strong>%s:</strong> %s</p>
            <p>%s</p>
            <p>%s</p>
            <table>
              <thead>
                <tr>
                  <th>%s</th>
                  <th>%s</th>
                  <th style="text-align:right">%s</th>
                  <th style="text-align:right">%s</th>
                  <th style="text-align:right">%s</th>
                </tr>
              </thead>
              <tbody>%s</tbody>
            </table>
            <div class="totals">
              <p>%s: %s</p>
              <p>%s%s</p>
              <p><strong>%s: %s</strong></p>
            </div>
            %s
            %s
            <div class="footer">
              <p>%s</p>
              %s
            </div>
            </body></html>',
            $companyBlock,
            $customerBlock,
            $this->escape($this->t($languageCode, 'offer')),
            $this->escape($offer->getNumber() ?? ''),
            $this->escape($this->t($languageCode, 'offer_number')),
            $this->escape($offer->getNumber() ?? ''),
            $this->escape($this->t($languageCode, 'date')),
            $issueDate,
            $this->escape($this->t($languageCode, 'valid_until')),
            $validUntil,
            nl2br($this->escape($greeting)),
            nl2br($this->escape($extraText)),
            $this->escape($this->t($languageCode, 'position')),
            $this->escape($this->t($languageCode, 'description')),
            $this->escape($this->t($languageCode, 'quantity')),
            $this->escape($this->t($languageCode, 'unit_price')),
            $this->escape($this->t($languageCode, 'total')),
            $rows,
            $this->escape($this->t($languageCode, 'subtotal')),
            $this->formatMoney($offer->getSubtotalCents(), $company, $languageCode),
            $this->escape($taxLine),
            $taxAmount ? ': ' . $taxAmount : '',
            $this->escape($this->t($languageCode, 'total')),
            $this->formatMoney($offer->getTotalCents(), $company, $languageCode),
            $closingTextBlock,
            $closingBlock,
            nl2br($this->escape($footerText)),
            ''
        );
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

}
