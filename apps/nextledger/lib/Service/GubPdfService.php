<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use OCA\NextLedger\Db\Expense;
use OCA\NextLedger\Db\ExpenseMapper;
use OCA\NextLedger\Db\FiscalYear;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCA\NextLedger\Db\Income;
use OCA\NextLedger\Db\IncomeMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IConfig;
use OCP\IUserSession;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class GubPdfService {
    public function __construct(
        private FiscalYearMapper $fiscalYearMapper,
        private IncomeMapper $incomeMapper,
        private ExpenseMapper $expenseMapper,
        private IConfig $config,
        private IUserSession $userSession,
    ) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function buildPdf(int $fiscalYearId): array {
        /** @var FiscalYear $year */
        $year = $this->fiscalYearMapper->find($fiscalYearId);
        $incomes = $this->incomeMapper->findByFiscalYearId($fiscalYearId);
        $expenses = $this->expenseMapper->findByFiscalYearId($fiscalYearId);

        $html = $this->renderHtml($year, $incomes, $expenses);
        $content = $this->renderPdf($html);
        $filename = sprintf('gub-%s.pdf', $year->getName() ?: $fiscalYearId);

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
     * @param Income[] $incomes
     * @param Expense[] $expenses
     */
    private function renderHtml(FiscalYear $year, array $incomes, array $expenses): string {
        $range = $this->formatRange($year->getDateStart(), $year->getDateEnd());
        $incomeRows = '';
        $incomeTotal = 0;
        foreach ($incomes as $income) {
            $incomeTotal += (int)($income->getAmountCents() ?? 0);
            $incomeName = $income->getName() ?: $income->getDescription() ?: 'Einnahme';
            $incomeRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td>%s</td></tr>',
                $this->escape($incomeName),
                $this->formatDate($income->getBookedAt()),
                $this->formatMoney($income->getAmountCents()),
                $this->escape($income->getStatus() ?: 'offen')
            );
        }

        $expenseRows = '';
        $expenseTotal = 0;
        foreach ($expenses as $expense) {
            $expenseTotal += (int)($expense->getAmountCents() ?? 0);
            $expenseRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td></tr>',
                $this->escape($expense->getName() ?: 'Ausgabe'),
                $this->formatDate($expense->getBookedAt()),
                $this->formatMoney($expense->getAmountCents())
            );
        }

        $profit = $incomeTotal - $expenseTotal;

        return sprintf(
            '<html><head><meta charset="UTF-8"><style>
                body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2933; }
                h1 { font-size: 20px; margin: 0 0 8px; }
                table { width: 100%%; border-collapse: collapse; margin-top: 12px; }
                th, td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; vertical-align: top; }
                th { text-align: left; background: #f3f4f6; }
                .section-title { margin-top: 16px; font-size: 14px; font-weight: 600; }
                .totals { margin-top: 12px; text-align: right; }
            </style></head><body>
            <h1>GÜB %s</h1>
            <p>Zeitraum: %s</p>

            <div class="section-title">Einnahmen</div>
            <table>
              <thead>
                <tr>
                  <th>Beschreibung</th>
                  <th>Datum</th>
                  <th style="text-align:right">Betrag</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>%s</tbody>
            </table>

            <div class="section-title">Ausgaben</div>
            <table>
              <thead>
                <tr>
                  <th>Beschreibung</th>
                  <th>Datum</th>
                  <th style="text-align:right">Betrag</th>
                </tr>
              </thead>
              <tbody>%s</tbody>
            </table>

            <div class="totals">
              <p>Einnahmen gesamt: %s</p>
              <p>Ausgaben gesamt: %s</p>
              <p><strong>Gewinn/Überschuss: %s</strong></p>
            </div>
            </body></html>',
            $this->escape($year->getName() ?: ''),
            $this->escape($range),
            $incomeRows,
            $expenseRows,
            $this->formatMoney($incomeTotal),
            $this->formatMoney($expenseTotal),
            $this->formatMoney($profit)
        );
    }

    private function formatRange(?int $start, ?int $end): string {
        if (!$start || !$end) {
            return '–';
        }
        return $this->formatDate($start) . ' – ' . $this->formatDate($end);
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

    private function escape(?string $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function formatMoney(?int $cents): string {
        if ($cents === null) {
            return '–';
        }
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }
}
