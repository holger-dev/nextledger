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
    ) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function buildPdf(int $offerId): array {
        /** @var Offer $offer */
        $offer = $this->offerMapper->find($offerId);
        $items = $this->offerItemMapper->findByOfferId($offerId);
        $customer = $this->loadCustomer($offer->getCustomerId());
        $company = $this->loadCompany();
        $texts = $this->loadTexts();
        $tax = $this->loadTax();
        $misc = $this->loadMisc();

        $html = $this->renderHtml($offer, $items, $customer, $company, $texts, $tax, $misc);
        $content = $this->renderPdf($html);
        $filename = sprintf('angebot-%s.pdf', $offer->getNumber() ?: $offerId);

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
        $issueDate = $offer->getIssueDate() ? date('d.m.Y', $offer->getIssueDate()) : '–';
        $validUntil = $offer->getValidUntil() ? date('d.m.Y', $offer->getValidUntil()) : '–';

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

        $taxLine = $offer->getIsSmallBusiness()
            ? ($tax?->getSmallBusinessNote() ?: 'Kleinunternehmerregelung')
            : sprintf('Steuer (%s%%)', number_format(($offer->getTaxRateBp() ?? 0) / 100, 2, ',', '.'));
        $taxAmount = $offer->getIsSmallBusiness() ? '' : $this->formatMoney($offer->getTaxCents());

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
                '<p>Mit freundlichen Grüßen</p><p>&nbsp;</p><p>%s</p>',
                $this->escape($ownerName)
            )
            : '<p>Mit freundlichen Grüßen</p>';

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
            <h1>Angebot %s</h1>
            <p><strong>Angebotsnummer:</strong> %s</p>
            <p><strong>Datum:</strong> %s<br><strong>Gültig bis:</strong> %s</p>
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
            </div>
            </body></html>',
            $companyBlock,
            $customerBlock,
            $this->escape($offer->getNumber() ?? ''),
            $this->escape($offer->getNumber() ?? ''),
            $issueDate,
            $validUntil,
            nl2br($this->escape($greeting)),
            nl2br($this->escape($extraText)),
            $rows,
            $this->formatMoney($offer->getSubtotalCents()),
            $this->escape($taxLine),
            $taxAmount ? ': ' . $taxAmount : '',
            $this->formatMoney($offer->getTotalCents()),
            $closingTextBlock,
            $closingBlock,
            nl2br($this->escape($footerText)),
            ''
        );
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

    private function loadCustomer(?int $customerId): ?Customer {
        if (!$customerId) {
            return null;
        }
        try {
            /** @var Customer $customer */
            $customer = $this->customerMapper->find($customerId);
            return $customer;
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return null;
        }
    }

    private function loadCompany(): ?Company {
        $items = $this->companyMapper->findAll(1, 0);
        return $items[0] ?? null;
    }

    private function loadTexts(): ?Texts {
        $items = $this->textsMapper->findAll(1, 0);
        return $items[0] ?? null;
    }

    private function loadTax(): ?TaxSetting {
        $items = $this->taxSettingMapper->findAll(1, 0);
        return $items[0] ?? null;
    }

    private function loadMisc(): ?MiscSetting {
        $items = $this->miscSettingMapper->findAll(1, 0);
        return $items[0] ?? null;
    }
}
