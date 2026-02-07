<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use DateTimeInterface;
use OCP\IConfig;
use OCP\AppFramework\Utility\ITimeFactory;

class NumberGenerator {
    private const APP_ID = 'nextledger';

    public function __construct(
        private IConfig $config,
        private ITimeFactory $timeFactory,
    ) {}

    public function nextInvoiceNumber(?DateTimeInterface $date = null): string {
        return $this->nextNumber('invoice', $date);
    }

    public function nextOfferNumber(?DateTimeInterface $date = null): string {
        return $this->nextNumber('offer', $date);
    }

    private function nextNumber(string $type, ?DateTimeInterface $date = null): string {
        $date = $date ?? $this->timeFactory->getDateTime('now');
        $dayKey = $date->format('Ymd');
        $configKey = $type . '_' . $dayKey;
        $current = (int) $this->config->getAppValue(self::APP_ID, $configKey, '0');
        $next = $current + 1;
        $this->config->setAppValue(self::APP_ID, $configKey, (string) $next);

        $sequence = str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        return $dayKey . '-' . $sequence;
    }
}
