<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use OCA\NextLedger\Db\EmailSetting;
use OCA\NextLedger\Db\EmailSettingMapper;
use OCP\IConfig;
use OCP\IUserSession;

class EmailSettingsService {
    public function __construct(
        private EmailSettingMapper $emailSettingMapper,
        private IConfig $config,
        private IUserSession $userSession,
    ) {}

    public function getSettings(): array {
        $userId = $this->getUserId();
        $settings = $userId ? $this->emailSettingMapper->findByUserId($userId) : null;
        $defaults = $this->getDefaults();

        return [
            'mode' => $settings?->getMode() ?: 'manual',
            'fromEmail' => $settings?->getFromEmail() ?: '',
            'replyToEmail' => $settings?->getReplyToEmail() ?: '',
            'defaultFromEmail' => $defaults['fromEmail'],
            'defaultReplyToEmail' => $defaults['replyToEmail'],
        ];
    }

    public function saveSettings(?string $mode, ?string $fromEmail, ?string $replyToEmail): array {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->getSettings();
        }

        $mode = $this->normalizeMode($mode);
        $fromEmail = $this->sanitizeEmail($fromEmail);
        $replyToEmail = $this->sanitizeEmail($replyToEmail);

        $settings = $this->emailSettingMapper->findByUserId($userId) ?? new EmailSetting();
        $settings->setUserId($userId);
        $settings->setMode($mode);
        $settings->setFromEmail($fromEmail);
        $settings->setReplyToEmail($replyToEmail);

        if ($settings->getId() === null) {
            $this->emailSettingMapper->insert($settings);
        } else {
            $this->emailSettingMapper->update($settings);
        }

        return $this->getSettings();
    }

    public function getEffectiveEmails(): array {
        $settings = $this->getSettings();
        $from = trim($settings['fromEmail']) ?: $settings['defaultFromEmail'];
        $replyTo = trim($settings['replyToEmail']) ?: $settings['defaultReplyToEmail'];

        return [
            'fromEmail' => $from,
            'replyToEmail' => $replyTo,
        ];
    }

    private function getDefaults(): array {
        $fromAddress = (string)$this->config->getSystemValue('mail_from_address', 'noreply');
        $mailDomain = (string)$this->config->getSystemValue('mail_domain', 'localhost');
        $fromEmail = '';
        if ($fromAddress !== '') {
            if (str_contains($fromAddress, '@')) {
                $fromEmail = $fromAddress;
            } else {
                $fromEmail = sprintf('%s@%s', $fromAddress, $mailDomain ?: 'localhost');
            }
        }

        $replyToEmail = '';
        $user = $this->userSession->getUser();
        if ($user && method_exists($user, 'getEMailAddress')) {
            $replyToEmail = (string)($user->getEMailAddress() ?? '');
        }

        return [
            'fromEmail' => $fromEmail,
            'replyToEmail' => $replyToEmail,
        ];
    }

    private function getUserId(): ?string {
        $user = $this->userSession->getUser();
        if ($user && method_exists($user, 'getUID')) {
            return $user->getUID();
        }

        return null;
    }

    private function normalizeMode(?string $mode): string {
        $mode = strtolower(trim((string)$mode));
        if ($mode === 'direct') {
            return 'direct';
        }

        return 'manual';
    }

    private function sanitizeEmail(?string $email): string {
        $email = trim((string)$email);
        if ($email === '') {
            return '';
        }

        return $email;
    }
}
