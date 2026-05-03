<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use OCA\NextLedger\Db\Company;
use OCP\IConfig;
use OCP\IUserSession;
use DateTimeImmutable;
use DateTimeZone;

class DocumentLocaleService {
    private const DEFAULT_LANGUAGE = 'de';

    private const LABELS = [
        'de' => [
            'dash' => '–',
            'invoice' => 'Rechnung',
            'invoice_filename' => 'rechnung',
            'advance_invoice' => 'Abschlagsrechnung',
            'final_invoice' => 'Schlussrechnung',
            'offer' => 'Angebot',
            'offer_filename' => 'angebot',
            'invoice_number' => 'Rechnungsnummer',
            'offer_number' => 'Angebotsnummer',
            'date' => 'Datum',
            'due_until' => 'Fällig bis',
            'valid_until' => 'Gültig bis',
            'position' => 'Position',
            'description' => 'Beschreibung',
            'quantity' => 'Menge',
            'unit_price' => 'Einzelpreis',
            'total' => 'Gesamt',
            'subtotal' => 'Zwischensumme',
            'tax' => 'Steuer',
            'small_business' => 'Kleinunternehmerregelung',
            'offer_reference' => 'Angebot',
            'from' => 'vom',
            'service_period' => 'Leistungszeitraum',
            'bank' => 'Bank',
            'account_holder' => 'Kontoinhaber',
            'closing_greeting' => 'Mit freundlichen Grüßen',
        ],
        'en' => [
            'dash' => '-',
            'invoice' => 'Invoice',
            'invoice_filename' => 'invoice',
            'advance_invoice' => 'Advance invoice',
            'final_invoice' => 'Final invoice',
            'offer' => 'Offer',
            'offer_filename' => 'offer',
            'invoice_number' => 'Invoice number',
            'offer_number' => 'Offer number',
            'date' => 'Date',
            'due_until' => 'Due by',
            'valid_until' => 'Valid until',
            'position' => 'Item',
            'description' => 'Description',
            'quantity' => 'Qty',
            'unit_price' => 'Unit price',
            'total' => 'Total',
            'subtotal' => 'Subtotal',
            'tax' => 'Tax',
            'small_business' => 'Small business regulation',
            'offer_reference' => 'Offer',
            'from' => 'from',
            'service_period' => 'Service period',
            'bank' => 'Bank',
            'account_holder' => 'Account holder',
            'closing_greeting' => 'Kind regards',
        ],
    ];

    public function __construct(
        private IConfig $config,
        private IUserSession $userSession,
    ) {}

    public function getDefaultLanguage(): string {
        $user = $this->userSession->getUser();
        if ($user && method_exists($user, 'getUID')) {
            $userLanguage = (string)$this->config->getUserValue($user->getUID(), 'core', 'lang', '');
            $normalized = $this->normalizeLanguageCode($userLanguage, false);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        if (method_exists($this->config, 'getSystemValueString')) {
            $systemLanguage = (string)$this->config->getSystemValueString('default_language', '');
            $normalized = $this->normalizeLanguageCode($systemLanguage, false);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return self::DEFAULT_LANGUAGE;
    }

    public function getCompanyLanguage(?Company $company): string {
        return $this->normalizeLanguageCode($company?->getLanguageCode(), true);
    }

    public function normalizeLanguageCode(?string $value, bool $fallbackToDefault = true): string {
        $normalized = strtolower(trim((string)($value ?? '')));
        if ($normalized === '') {
            return $fallbackToDefault ? $this->getDefaultLanguage() : '';
        }
        if (str_starts_with($normalized, 'en')) {
            return 'en';
        }
        if (str_starts_with($normalized, 'de')) {
            return 'de';
        }

        return $fallbackToDefault ? $this->getDefaultLanguage() : '';
    }

    public function t(string $languageCode, string $key): string {
        $language = $this->normalizeLanguageCode($languageCode);
        return self::LABELS[$language][$key] ?? self::LABELS[self::DEFAULT_LANGUAGE][$key] ?? $key;
    }

    public function formatDate(?int $value, string $languageCode): string {
        if (!$value) {
            return $this->t($languageCode, 'dash');
        }

        $timezone = $this->getUserTimezone();
        try {
            $date = (new DateTimeImmutable('@' . $value))->setTimezone(new DateTimeZone($timezone));
        } catch (\Throwable $e) {
            $date = (new DateTimeImmutable('@' . $value))->setTimezone(new DateTimeZone('UTC'));
        }

        return $this->normalizeLanguageCode($languageCode) === 'en'
            ? $date->format('m/d/Y')
            : $date->format('d.m.Y');
    }

    public function formatPercent(float $value, string $languageCode): string {
        return $this->normalizeLanguageCode($languageCode) === 'en'
            ? number_format($value, 2, '.', ',')
            : number_format($value, 2, ',', '.');
    }

    public function formatMoney(?int $cents, ?string $currencyCode, string $languageCode): string {
        if ($cents === null) {
            return $this->t($languageCode, 'dash');
        }

        $currency = strtoupper(trim((string)($currencyCode ?? '')));
        if ($currency === '') {
            $currency = 'EUR';
        }

        $isEnglish = $this->normalizeLanguageCode($languageCode) === 'en';
        $amount = $isEnglish
            ? number_format($cents / 100, 2, '.', ',')
            : number_format($cents / 100, 2, ',', '.');

        if ($isEnglish) {
            return match ($currency) {
                'EUR' => '€' . $amount,
                'USD' => '$' . $amount,
                'GBP' => '£' . $amount,
                'CHF' => 'CHF ' . $amount,
                'NGN' => '₦' . $amount,
                'JPY' => '¥' . $amount,
                'CNY' => '¥' . $amount,
                'INR' => '₹' . $amount,
                'CAD' => 'CA$' . $amount,
                'AUD' => 'A$' . $amount,
                'NZD' => 'NZ$' . $amount,
                'BRL' => 'R$' . $amount,
                'MXN' => 'MX$' . $amount,
                'ZAR' => 'R ' . $amount,
                'SEK', 'NOK', 'DKK' => $currency . ' ' . $amount,
                default => $currency . ' ' . $amount,
            };
        }

        return match ($currency) {
            'EUR' => $amount . ' €',
            'USD' => '$' . $amount,
            'GBP' => '£' . $amount,
            'CHF' => $amount . ' CHF',
            'NGN' => '₦' . $amount,
            'JPY' => '¥' . $amount,
            'CNY' => '¥' . $amount,
            'INR' => '₹' . $amount,
            'CAD' => 'CA$' . $amount,
            'AUD' => 'A$' . $amount,
            'NZD' => 'NZ$' . $amount,
            'BRL' => 'R$' . $amount,
            'MXN' => 'MX$' . $amount,
            'ZAR' => $amount . ' R',
            'SEK', 'NOK', 'DKK' => $amount . ' ' . $currency,
            default => $amount . ' ' . $currency,
        };
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
