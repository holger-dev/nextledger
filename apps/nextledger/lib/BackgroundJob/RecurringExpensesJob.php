<?php

declare(strict_types=1);

namespace OCA\NextLedger\BackgroundJob;

use OCA\NextLedger\Db\Expense;
use OCA\NextLedger\Db\ExpenseMapper;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Issue #23: recurring expenses (subscriptions, rent, hosting, ...).
 *
 * An expense with recurring_interval != 'none' acts as a template. This job
 * runs daily and creates concrete expense bookings for every interval step
 * that has become due, up to (optionally) recurring_until. Generated bookings
 * reference the template via recurring_parent_id and never recur themselves.
 */
class RecurringExpensesJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private ExpenseMapper $expenseMapper,
        private FiscalYearMapper $fiscalYearMapper,
    ) {
        parent::__construct($time);
        // run at most once a day
        $this->setInterval(24 * 3600);
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
    }

    protected function run($argument): void {
        $now = $this->time->getTime();
        foreach ($this->expenseMapper->findRecurringTemplates() as $template) {
            try {
                $this->materializeDueBookings($template, $now);
            } catch (\Throwable $e) {
                // one broken template must not block the others
                \OC::$server->get(\Psr\Log\LoggerInterface::class)->warning(
                    'NextLedger recurring expense failed: ' . $e->getMessage(),
                    ['app' => 'nextledger']
                );
            }
        }
    }

    private function materializeDueBookings(Expense $template, int $now): void {
        $interval = strtolower((string)$template->getRecurringInterval());
        if (!in_array($interval, ['monthly', 'quarterly', 'yearly'], true)) {
            return;
        }
        $anchor = (int)($template->getLastRecurredAt() ?: $template->getBookedAt() ?: $template->getCreatedAt());
        if ($anchor <= 0) {
            return;
        }
        $until = (int)($template->getRecurringUntil() ?: 0);

        $next = $this->advance($anchor, $interval);
        $created = 0;
        // safety bound: create at most 24 bookings per run per template
        while ($next <= $now && $created < 24) {
            if ($until > 0 && $next > $until) {
                break;
            }
            $this->createBooking($template, $next);
            $anchor = $next;
            $next = $this->advance($anchor, $interval);
            $created++;
        }

        if ($created > 0) {
            $template->setLastRecurredAt($anchor);
            $template->setUpdatedAt($now);
            $this->expenseMapper->update($template);
        }
    }

    private function advance(int $timestamp, string $interval): int {
        $date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('UTC'));
        $stepped = match ($interval) {
            'monthly' => $date->modify('+1 month'),
            'quarterly' => $date->modify('+3 months'),
            'yearly' => $date->modify('+1 year'),
        };
        return $stepped->getTimestamp();
    }

    private function createBooking(Expense $template, int $bookedAt): void {
        $companyId = (int)$template->getCompanyId();
        // book into the fiscal year matching the date, else the template's year
        $year = $this->fiscalYearMapper->findByDate($bookedAt, $companyId);
        $fiscalYearId = $year ? (int)$year->getId() : (int)$template->getFiscalYearId();

        $booking = new Expense();
        $booking->setCompanyId($companyId);
        $booking->setFiscalYearId($fiscalYearId);
        $booking->setName($template->getName());
        $booking->setDescription($template->getDescription());
        $booking->setAmountCents($template->getAmountCents());
        $booking->setBookedAt($bookedAt);
        $booking->setRecurringInterval('none');
        $booking->setRecurringParentId((int)$template->getId());
        $booking->setCreatedAt(time());
        $booking->setUpdatedAt(time());
        $this->expenseMapper->insert($booking);
    }
}
