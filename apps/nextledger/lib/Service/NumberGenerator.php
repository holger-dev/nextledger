<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use DateTimeInterface;
use OCA\NextLedger\Db\CompanyMapper;
use OCP\IConfig;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Generates document numbers.
 *
 * Issue #14: invoice numbers can follow a per-company scheme with placeholders.
 * Supported placeholders:
 *   {YYYY} {YY} {MM} {DD}  — issue date parts
 *   {SEQ} {SEQ3}..{SEQ6}   — sequential counter, optionally zero-padded
 * The counter scope follows the date placeholders used: a scheme containing
 * {DD} counts per day, {MM} per month, {YYYY}/{YY} per year, none = global.
 * Without a scheme the legacy format YYYYMMDD-#### is used.
 */
class NumberGenerator {
    private const APP_ID = 'nextledger';

    public function __construct(
        private IConfig $config,
        private ITimeFactory $timeFactory,
        private ActiveCompanyService $activeCompanyService,
        private CompanyMapper $companyMapper,
    ) {}

    public function nextInvoiceNumber(?DateTimeInterface $date = null): string {
        $scheme = $this->getCompanyScheme();
        if ($scheme !== null) {
            return $this->nextSchemeNumber('invoice', $scheme, $date);
        }
        return $this->nextNumber('invoice', $date);
    }

    public function nextOfferNumber(?DateTimeInterface $date = null): string {
        return $this->nextNumber('offer', $date);
    }

    /**
     * Render a preview of a scheme without consuming a counter value.
     */
    public function previewScheme(string $scheme, ?DateTimeInterface $date = null): string {
        $date = $date ?? $this->timeFactory->getDateTime('now');
        return $this->renderScheme($scheme, $date, 1);
    }

    public function isValidScheme(string $scheme): bool {
        if (trim($scheme) === '' || strlen($scheme) > 64) {
            return false;
        }
        // must contain exactly one sequence placeholder
        return preg_match_all('/\{SEQ[3-6]?\}/', $scheme) === 1;
    }

    private function getCompanyScheme(): ?string {
        try {
            $companyId = $this->activeCompanyService->getActiveCompanyId();
            $company = $this->companyMapper->find($companyId);
            $scheme = trim((string)($company->getNumberScheme() ?? ''));
            return $scheme !== '' && $this->isValidScheme($scheme) ? $scheme : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function nextSchemeNumber(string $type, string $scheme, ?DateTimeInterface $date = null): string {
        $date = $date ?? $this->timeFactory->getDateTime('now');
        $companyId = $this->activeCompanyService->getActiveCompanyId();

        // Counter scope follows the finest date placeholder in the scheme
        if (str_contains($scheme, '{DD}')) {
            $scopeKey = $date->format('Ymd');
        } elseif (str_contains($scheme, '{MM}')) {
            $scopeKey = $date->format('Ym');
        } elseif (str_contains($scheme, '{YYYY}') || str_contains($scheme, '{YY}')) {
            $scopeKey = $date->format('Y');
        } else {
            $scopeKey = 'all';
        }

        $configKey = sprintf('%s_scheme_%d_%s', $type, $companyId, $scopeKey);
        $current = (int)$this->config->getAppValue(self::APP_ID, $configKey, '0');
        $next = $current + 1;
        $this->config->setAppValue(self::APP_ID, $configKey, (string)$next);

        return $this->renderScheme($scheme, $date, $next);
    }

    private function renderScheme(string $scheme, DateTimeInterface $date, int $sequence): string {
        $replacements = [
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
            '{SEQ6}' => str_pad((string)$sequence, 6, '0', STR_PAD_LEFT),
            '{SEQ5}' => str_pad((string)$sequence, 5, '0', STR_PAD_LEFT),
            '{SEQ4}' => str_pad((string)$sequence, 4, '0', STR_PAD_LEFT),
            '{SEQ3}' => str_pad((string)$sequence, 3, '0', STR_PAD_LEFT),
            '{SEQ}' => (string)$sequence,
        ];
        return strtr($scheme, $replacements);
    }

    private function nextNumber(string $type, ?DateTimeInterface $date = null): string {
        $date = $date ?? $this->timeFactory->getDateTime('now');
        $dayKey = $date->format('Ymd');
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $configKey = $type . '_' . $companyId . '_' . $dayKey;
        $current = (int) $this->config->getAppValue(self::APP_ID, $configKey, '0');
        $next = $current + 1;
        $this->config->setAppValue(self::APP_ID, $configKey, (string) $next);

        $sequence = str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        return $dayKey . '-' . $sequence;
    }
}
