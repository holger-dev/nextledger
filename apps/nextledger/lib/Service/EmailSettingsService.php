<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\EmailSetting;
use OCA\NextLedger\Db\EmailSettingMapper;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Mail\Provider\IManager as IMailProviderManager;
use OCP\Mail\Provider\IService as IMailService;

class EmailSettingsService {
    public function __construct(
        private EmailSettingMapper $emailSettingMapper,
        private IConfig $config,
        private IUserSession $userSession,
        private ActiveCompanyService $activeCompanyService,
        private ?IMailProviderManager $mailProviderManager = null,
    ) {}

    public function getSettings(): array {
        $userId = $this->getUserId();
        $activeCompanyId = $this->activeCompanyService->getActiveCompanyId();
        $defaults = $this->getDefaults();
        $companies = $this->activeCompanyService->getCompanies();

        $global = null;
        $companySettings = [];
        if ($userId !== null) {
            $global = $this->emailSettingMapper->findByUserAndCompanyId($userId, null);
            foreach ($this->emailSettingMapper->findAllByUserId($userId) as $entry) {
                $companyId = $entry->getCompanyId();
                if ($companyId !== null) {
                    $companySettings[(int)$companyId] = $entry;
                }
            }
        }

        $globalMode = $this->normalizeMode($global?->getMode());
        $activeSetting = $companySettings[$activeCompanyId] ?? null;
        $activeMode = $this->normalizeMode($activeSetting?->getMode() ?: $globalMode);
        $activeProviderId = $activeSetting?->getProviderId() ?: ($global?->getProviderId() ?: '');
        $activeServiceId = $activeSetting?->getServiceId() ?: ($global?->getServiceId() ?: '');
        $storedFrom = trim((string)($global?->getFromEmail() ?: ''));
        $storedReplyTo = trim((string)($global?->getReplyToEmail() ?: ''));
        $autoFrom = $defaults['fromEmail'];
        $autoReplyTo = $defaults['replyToEmail'];
        if ($activeMode === 'nextcloud_mail') {
            $serviceAddress = $this->resolveProviderServiceAddress((string)$userId, $activeProviderId, $activeServiceId);
            if ($serviceAddress !== '') {
                $autoFrom = $serviceAddress;
                $autoReplyTo = $serviceAddress;
            }
        }
        $effectiveFrom = $storedFrom !== '' ? $storedFrom : $autoFrom;
        $effectiveReplyTo = $storedReplyTo !== '' ? $storedReplyTo : $autoReplyTo;

        $mappingRows = array_map(function (Company $company) use ($companySettings, $globalMode, $global): array {
            $companyId = (int)$company->getId();
            $entry = $companySettings[$companyId] ?? null;
            $mode = $this->normalizeMode($entry?->getMode() ?: $globalMode);

            return [
                'companyId' => $companyId,
                'companyName' => (string)($company->getName() ?: 'Company'),
                'mode' => $mode,
                'providerId' => ($entry?->getProviderId() ?: ($global?->getProviderId() ?: '')),
                'serviceId' => ($entry?->getServiceId() ?: ($global?->getServiceId() ?: '')),
            ];
        }, $companies);

        return [
            'mode' => $activeMode,
            'providerId' => $activeProviderId,
            'serviceId' => $activeServiceId,
            'fromEmail' => $storedFrom,
            'replyToEmail' => $storedReplyTo,
            'defaultFromEmail' => $defaults['fromEmail'],
            'defaultReplyToEmail' => $defaults['replyToEmail'],
            'autoFromEmail' => $autoFrom,
            'autoReplyToEmail' => $autoReplyTo,
            'effectiveFromEmail' => $effectiveFrom,
            'effectiveReplyToEmail' => $effectiveReplyTo,
            'activeCompanyId' => $activeCompanyId,
            'companyMappings' => $mappingRows,
            'mailProviders' => $this->listMailProviderServices((string)$userId),
        ];
    }

    public function saveSettings(?string $mode, ?string $fromEmail, ?string $replyToEmail, ?array $companyMappings = null): array {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->getSettings();
        }

        $global = $this->emailSettingMapper->findByUserAndCompanyId($userId, null) ?? new EmailSetting();
        $global->setUserId($userId);
        $global->setCompanyId(null);
        if ($mode !== null) {
            $global->setMode($this->normalizeMode($mode));
        } elseif (!$global->getMode()) {
            $global->setMode('manual');
        }
        if ($fromEmail !== null) {
            $global->setFromEmail($this->sanitizeEmail($fromEmail));
        }
        if ($replyToEmail !== null) {
            $global->setReplyToEmail($this->sanitizeEmail($replyToEmail));
        }

        if ($global->getId() === null) {
            $this->emailSettingMapper->insert($global);
        } else {
            $this->emailSettingMapper->update($global);
        }

        if (is_array($companyMappings)) {
            foreach ($companyMappings as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }
                $companyId = (int)($mapping['companyId'] ?? 0);
                if ($companyId <= 0) {
                    continue;
                }

                $entry = $this->emailSettingMapper->findByUserAndCompanyId($userId, $companyId) ?? new EmailSetting();
                $entry->setUserId($userId);
                $entry->setCompanyId($companyId);

                $companyMode = $this->normalizeMode((string)($mapping['mode'] ?? $global->getMode() ?? 'manual'));
                $entry->setMode($companyMode);

                $providerId = $this->sanitizeId((string)($mapping['providerId'] ?? ''));
                $serviceId = $this->sanitizeId((string)($mapping['serviceId'] ?? ''));
                if ($companyMode !== 'nextcloud_mail') {
                    $providerId = '';
                    $serviceId = '';
                }
                $entry->setProviderId($providerId);
                $entry->setServiceId($serviceId);

                if ($entry->getId() === null) {
                    $this->emailSettingMapper->insert($entry);
                } else {
                    $this->emailSettingMapper->update($entry);
                }
            }
        }

        return $this->getSettings();
    }

    public function getEffectiveEmails(): array {
        $settings = $this->getSettings();
        $from = trim((string)($settings['effectiveFromEmail'] ?? ''));
        $replyTo = trim((string)($settings['effectiveReplyToEmail'] ?? ''));

        return [
            'fromEmail' => $from,
            'replyToEmail' => $replyTo,
        ];
    }

    public function getConfiguredOverrides(): array {
        $settings = $this->getSettings();

        return [
            'fromEmail' => trim((string)($settings['fromEmail'] ?? '')),
            'replyToEmail' => trim((string)($settings['replyToEmail'] ?? '')),
        ];
    }

    public function getDeliveryConfig(): array {
        $settings = $this->getSettings();

        return [
            'mode' => $this->normalizeMode((string)($settings['mode'] ?? 'manual')),
            'providerId' => $this->sanitizeId((string)($settings['providerId'] ?? '')),
            'serviceId' => $this->sanitizeId((string)($settings['serviceId'] ?? '')),
        ];
    }

    public function getCurrentUserId(): ?string {
        return $this->getUserId();
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
        if ($mode === 'direct' || $mode === 'admin_smtp') {
            return 'admin_smtp';
        }
        if ($mode === 'nextcloud_mail') {
            return 'nextcloud_mail';
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

    private function sanitizeId(?string $value): string {
        return trim((string)$value);
    }

    private function listMailProviderServices(string $userId): array {
        if ($userId === '' || $this->mailProviderManager === null || !$this->mailProviderManager->has()) {
            return [];
        }

        $providers = $this->mailProviderManager->providers();
        $servicesByProvider = $this->mailProviderManager->services($userId);

        $result = [];
        foreach ($servicesByProvider as $providerId => $services) {
            if (!is_array($services)) {
                continue;
            }

            $providerLabel = $providerId;
            if (isset($providers[$providerId])) {
                $providerLabel = $providers[$providerId]->label();
            }

            $providerServices = [];
            foreach ($services as $serviceId => $service) {
                if (!$service instanceof IMailService) {
                    continue;
                }
                if (!$service->capable('MessageSend')) {
                    continue;
                }

                $primaryAddress = $service->getPrimaryAddress();
                $providerServices[] = [
                    'serviceId' => (string)$serviceId,
                    'label' => $service->getLabel(),
                    'address' => $primaryAddress?->getAddress() ?: '',
                ];
            }

            if (!empty($providerServices)) {
                usort($providerServices, static fn(array $a, array $b) => strcasecmp((string)$a['label'], (string)$b['label']));
                $result[] = [
                    'providerId' => (string)$providerId,
                    'label' => $providerLabel,
                    'services' => $providerServices,
                ];
            }
        }

        usort($result, static fn(array $a, array $b) => strcasecmp((string)$a['label'], (string)$b['label']));
        return $result;
    }

    private function resolveProviderServiceAddress(string $userId, string $providerId, string $serviceId): string {
        if ($userId === '' || $serviceId === '' || $this->mailProviderManager === null || !$this->mailProviderManager->has()) {
            return '';
        }

        $service = $this->mailProviderManager->findServiceById($userId, $serviceId, $providerId ?: null);
        if ($service === null) {
            return '';
        }

        return trim((string)($service->getPrimaryAddress()?->getAddress() ?: ''));
    }
}
