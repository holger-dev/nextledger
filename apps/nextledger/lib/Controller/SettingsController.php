<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\BaseMapper;
use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\CompanyMapper;
use OCA\NextLedger\Db\DocumentSetting;
use OCA\NextLedger\Db\DocumentSettingMapper;
use OCA\NextLedger\Db\MiscSetting;
use OCA\NextLedger\Db\MiscSettingMapper;
use OCA\NextLedger\Db\TaxSetting;
use OCA\NextLedger\Db\TaxSettingMapper;
use OCA\NextLedger\Db\Texts;
use OCA\NextLedger\Db\TextsMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCA\NextLedger\Service\EmailSettingsService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;

class SettingsController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private CompanyMapper $companyMapper,
        private TextsMapper $textsMapper,
        private TaxSettingMapper $taxSettingMapper,
        private MiscSettingMapper $miscSettingMapper,
        private DocumentSettingMapper $documentSettingMapper,
        private ActiveCompanyService $activeCompanyService,
        private EmailSettingsService $emailSettingsService,
        private IUserManager $userManager,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getCompanies(): JSONResponse {
        $legacyCompanies = $this->activeCompanyService->getLegacyCompanies();
        $companies = array_map(
            fn(Company $company) => $this->entityToCompanyArray($company),
            $this->activeCompanyService->getCompanies()
        );

        return new JSONResponse([
            'companies' => $companies,
            'activeCompanyId' => $this->activeCompanyService->getActiveCompanyId(),
            'ownershipRecoveryRequired' => !empty($legacyCompanies),
            'currentUserId' => $this->emailSettingsService->getCurrentUserId(),
            'legacyCompanies' => array_map(function (Company $company): array {
                return [
                    'id' => (int)$company->getId(),
                    'name' => (string)($company->getName() ?: ''),
                    'groupName' => (string)($company->getGroupName() ?: ''),
                    'ownerName' => (string)($company->getOwnerName() ?: ''),
                ];
            }, $legacyCompanies),
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getCompanyOwnershipRecovery(): JSONResponse {
        $legacyCompanies = $this->activeCompanyService->getLegacyCompanies();
        $companies = array_map(function (Company $company): array {
            return [
                'id' => (int)$company->getId(),
                'name' => (string)($company->getName() ?: ''),
                'groupName' => (string)($company->getGroupName() ?: ''),
                'ownerName' => (string)($company->getOwnerName() ?: ''),
            ];
        }, $legacyCompanies);

        return new JSONResponse([
            'required' => !empty($companies),
            'currentUserId' => $this->emailSettingsService->getCurrentUserId(),
            'companies' => $companies,
            'availableUsers' => $this->listAvailableUsers(),
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function claimCompanyOwnershipRecovery(?string $userId = null): JSONResponse {
        $params = $this->request->getParams();
        if ($userId === null) {
            $userId = $params['userId'] ?? null;
        }

        try {
            $targetUserId = trim((string)($userId ?: $this->emailSettingsService->getCurrentUserId() ?: ''));
            $this->activeCompanyService->assignLegacyCompaniesToUser($targetUserId);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return $this->getCompanyOwnershipRecovery();
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function createCompany(
        ?string $name = null,
        ?string $groupName = null,
        ?string $currencyCode = null,
        ?array $sharedUserIds = null,
        ?string $ownerName = null,
        ?string $street = null,
        ?string $houseNumber = null,
        ?string $zip = null,
        ?string $city = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $vatId = null,
        ?string $taxId = null,
    ): JSONResponse {
        $company = new Company();
        $company->setName($name ?: 'Neue Firma');
        $company->setGroupName($groupName);
        $company->setCurrencyCode($this->normalizeCurrencyCode($currencyCode));
        $company->setOwnerUserId($this->emailSettingsService->getCurrentUserId());
        $company->setOwnerName($ownerName);
        $company->setStreet($street);
        $company->setHouseNumber($houseNumber);
        $company->setZip($zip);
        $company->setCity($city);
        $company->setEmail($email);
        $company->setPhone($phone);
        $company->setVatId($vatId);
        $company->setTaxId($taxId);

        /** @var Company $saved */
        $saved = $this->companyMapper->insert($company);
        if (is_array($sharedUserIds)) {
            $this->activeCompanyService->setActiveCompanyId((int)$saved->getId());
            $this->activeCompanyService->saveSharedUserIds((int)$saved->getId(), $sharedUserIds);
        }
        $this->activeCompanyService->setActiveCompanyId((int)$saved->getId());

        return new JSONResponse($this->entityToCompanyArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function activateCompany(string $id): JSONResponse {
        try {
            $company = $this->activeCompanyService->setActiveCompanyId((int)$id);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['message' => 'Firma nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse($this->entityToCompanyArray($company));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function deleteCompany(string $id): JSONResponse {
        $companyId = (int)$id;
        $companies = $this->activeCompanyService->getCompanies();
        if (count($companies) <= 1) {
            return new JSONResponse(['message' => 'Mindestens eine Firma muss vorhanden bleiben.'], Http::STATUS_BAD_REQUEST);
        }

        $target = null;
        foreach ($companies as $company) {
            if ((int)$company->getId() === $companyId) {
                $target = $company;
                break;
            }
        }
        if ($target === null) {
            return new JSONResponse(['message' => 'Firma nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $activeId = $this->activeCompanyService->getActiveCompanyId();
        if ($activeId === $companyId) {
            return new JSONResponse(['message' => 'Aktive Firma kann nicht gelöscht werden.'], Http::STATUS_BAD_REQUEST);
        }
        if (!$this->activeCompanyService->canManageCompanyUsers($companyId)) {
            return new JSONResponse(['message' => 'Keine Berechtigung zum Löschen der Firma.'], Http::STATUS_FORBIDDEN);
        }
        $this->activeCompanyService->deleteCompanyShares($companyId);
        $this->companyMapper->delete($target);

        return new JSONResponse(['status' => 'ok']);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getCompany(): JSONResponse {
        try {
            $company = $this->activeCompanyService->getActiveCompany();
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
        }

        return new JSONResponse($this->entityToCompanyArray($company));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveCompany(
        ?string $name = null,
        ?string $groupName = null,
        ?string $currencyCode = null,
        ?array $sharedUserIds = null,
        ?string $ownerName = null,
        ?string $street = null,
        ?string $houseNumber = null,
        ?string $zip = null,
        ?string $city = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $vatId = null,
        ?string $taxId = null,
    ): JSONResponse {
        $company = $this->activeCompanyService->getActiveCompany();
        $company->setName($name);
        $company->setGroupName($groupName);
        $company->setCurrencyCode($this->normalizeCurrencyCode($currencyCode));
        $company->setOwnerName($ownerName);
        $company->setStreet($street);
        $company->setHouseNumber($houseNumber);
        $company->setZip($zip);
        $company->setCity($city);
        $company->setEmail($email);
        $company->setPhone($phone);
        $company->setVatId($vatId);
        $company->setTaxId($taxId);
        /** @var Company $saved */
        $saved = $this->companyMapper->update($company);
        if (is_array($sharedUserIds)) {
            $this->activeCompanyService->saveSharedUserIds((int)$saved->getId(), $sharedUserIds);
        }

        return new JSONResponse($this->entityToCompanyArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getTexts(): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $texts = $this->getScopedSingleton($this->textsMapper, Texts::class, $companyId);
        return new JSONResponse($texts);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveTexts(
        ?string $invoiceGreeting = null,
        ?string $offerGreeting = null,
        ?string $footerText = null,
        ?string $offerClosingText = null,
        ?string $invoiceClosingText = null,
        ?string $offerEmailSubject = null,
        ?string $offerEmailBody = null,
        ?string $invoiceEmailSubject = null,
        ?string $invoiceEmailBody = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        /** @var Texts $texts */
        $texts = $this->getScopedSingletonEntity($this->textsMapper, Texts::class, $companyId);
        $texts->setCompanyId($companyId);
        $texts->setInvoiceGreeting($invoiceGreeting);
        $texts->setOfferGreeting($offerGreeting);
        $texts->setFooterText($footerText);
        $texts->setOfferClosingText($offerClosingText);
        $texts->setInvoiceClosingText($invoiceClosingText);
        $texts->setOfferEmailSubject($offerEmailSubject);
        $texts->setOfferEmailBody($offerEmailBody);
        $texts->setInvoiceEmailSubject($invoiceEmailSubject);
        $texts->setInvoiceEmailBody($invoiceEmailBody);
        $saved = $this->persistScopedSingleton($this->textsMapper, $texts);

        return new JSONResponse($saved);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getTax(): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $tax = $this->getScopedSingleton($this->taxSettingMapper, TaxSetting::class, $companyId);
        $tax['isSmallBusiness'] = (bool)($tax['isSmallBusiness'] ?? false);
        return new JSONResponse($tax);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveTax(
        ?int $vatRateBp = null,
        ?bool $isSmallBusiness = null,
        ?string $smallBusinessNote = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        /** @var TaxSetting $tax */
        $tax = $this->getScopedSingletonEntity($this->taxSettingMapper, TaxSetting::class, $companyId);
        $tax->setCompanyId($companyId);
        $params = $this->request->getParams();
        if ($vatRateBp === null) {
            $vatRateBp = (int)($params['vatRateBp'] ?? $this->request->getParam('vatRateBp', 0));
        }
        $rawSmallBusiness = $isSmallBusiness;
        if ($rawSmallBusiness === null) {
            $rawSmallBusiness = $params['isSmallBusiness'] ?? $this->request->getParam('isSmallBusiness', null);
        }
        if ($smallBusinessNote === null) {
            $smallBusinessNote = $params['smallBusinessNote'] ?? $this->request->getParam('smallBusinessNote', null);
        }
        if (is_string($rawSmallBusiness)) {
            $rawSmallBusiness = in_array(strtolower($rawSmallBusiness), ['1', 'true', 'yes', 'on'], true);
        }
        $tax->setVatRateBp($vatRateBp);
        $tax->setIsSmallBusiness((bool)$rawSmallBusiness);
        $tax->setSmallBusinessNote($smallBusinessNote);
        $saved = $this->persistScopedSingleton($this->taxSettingMapper, $tax);
        $saved['isSmallBusiness'] = (bool)($saved['isSmallBusiness'] ?? false);

        return new JSONResponse($saved);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getMisc(): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $misc = $this->getScopedSingleton($this->miscSettingMapper, MiscSetting::class, $companyId);
        return new JSONResponse($misc);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveMisc(
        ?int $paymentTermsDays = null,
        ?string $bankName = null,
        ?string $iban = null,
        ?string $bic = null,
        ?string $accountHolder = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        /** @var MiscSetting $misc */
        $misc = $this->getScopedSingletonEntity($this->miscSettingMapper, MiscSetting::class, $companyId);
        $misc->setCompanyId($companyId);
        $misc->setPaymentTermsDays($paymentTermsDays);
        $misc->setBankName($bankName);
        $misc->setIban($iban);
        $misc->setBic($bic);
        $misc->setAccountHolder($accountHolder);
        $saved = $this->persistScopedSingleton($this->miscSettingMapper, $misc);

        return new JSONResponse($saved);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getDocuments(): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        /** @var DocumentSetting $setting */
        $setting = $this->getScopedSingletonEntity($this->documentSettingMapper, DocumentSetting::class, $companyId);

        return new JSONResponse([
            'autoStorePdfs' => (bool)$setting->getAutoStorePdfs(),
            'keepPdfVersions' => (bool)$setting->getKeepPdfVersions(),
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveDocuments(?bool $autoStorePdfs = null, ?bool $keepPdfVersions = null): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        /** @var DocumentSetting $setting */
        $setting = $this->getScopedSingletonEntity($this->documentSettingMapper, DocumentSetting::class, $companyId);
        $setting->setCompanyId($companyId);

        $params = $this->request->getParams();
        if ($autoStorePdfs === null) {
            $autoStorePdfs = (bool)($params['autoStorePdfs'] ?? false);
        }
        if ($keepPdfVersions === null) {
            $keepPdfVersions = (bool)($params['keepPdfVersions'] ?? false);
        }

        $setting->setAutoStorePdfs((bool)$autoStorePdfs);
        $setting->setKeepPdfVersions((bool)$keepPdfVersions);
        $saved = $this->persistScopedSingleton($this->documentSettingMapper, $setting);

        return new JSONResponse([
            'autoStorePdfs' => (bool)($saved['autoStorePdfs'] ?? false),
            'keepPdfVersions' => (bool)($saved['keepPdfVersions'] ?? false),
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getEmailBehavior(): JSONResponse {
        return new JSONResponse($this->emailSettingsService->getSettings());
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveEmailBehavior(
        ?string $mode = null,
        ?string $fromEmail = null,
        ?string $replyToEmail = null,
        ?array $companyMappings = null,
    ): JSONResponse {
        $params = $this->request->getParams();
        if ($mode === null) {
            $mode = $params['mode'] ?? null;
        }
        if ($fromEmail === null) {
            $fromEmail = $params['fromEmail'] ?? null;
        }
        if ($replyToEmail === null) {
            $replyToEmail = $params['replyToEmail'] ?? null;
        }
        if ($companyMappings === null) {
            $rawMappings = $params['companyMappings'] ?? null;
            if (is_array($rawMappings)) {
                $companyMappings = $rawMappings;
            }
        }

        return new JSONResponse($this->emailSettingsService->saveSettings($mode, $fromEmail, $replyToEmail, $companyMappings));
    }

    private function getScopedSingleton(BaseMapper $mapper, string $entityClass, int $companyId): array {
        $entity = $this->getScopedSingletonEntity($mapper, $entityClass, $companyId);
        return $this->entityToArray($entity);
    }

    private function getScopedSingletonEntity(BaseMapper $mapper, string $entityClass, int $companyId): object {
        $items = $mapper->findAllByCompanyId($companyId, 1, 0);
        if (!empty($items)) {
            return $items[0];
        }

        $entity = new $entityClass();
        if (method_exists($entity, 'setCompanyId')) {
            $entity->setCompanyId($companyId);
        }
        return $entity;
    }

    private function persistScopedSingleton(BaseMapper $mapper, object $entity): array {
        if ($entity->getId() === null) {
            $entity = $mapper->insert($entity);
        } else {
            $entity = $mapper->update($entity);
        }

        return $this->entityToArray($entity);
    }

    private function entityToArray(object $entity): array {
        if (method_exists($entity, 'jsonSerialize')) {
            /** @var array $data */
            $data = $entity->jsonSerialize();
            return $data;
        }

        // NC 32 Entity has public properties but no jsonSerialize
        return get_object_vars($entity);
    }

    private function entityToCompanyArray(Company $company): array {
        $data = $this->entityToArray($company);
        $companyId = (int)($company->getId() ?? 0);
        $data['currencyCode'] = $this->normalizeCurrencyCode($data['currencyCode'] ?? null);
        $data['sharedUserIds'] = $companyId > 0 ? $this->activeCompanyService->getSharedUserIds($companyId) : [];
        $data['canManageUsers'] = $companyId > 0 ? $this->activeCompanyService->canManageCompanyUsers($companyId) : false;
        $data['isOwner'] = $data['canManageUsers'];
        $data['availableUsers'] = $this->listAvailableUsers();

        return $data;
    }

    private function listAvailableUsers(): array {
        $users = $this->userManager->search('');
        $result = array_map(function ($user): array {
            $userId = method_exists($user, 'getUID') ? (string)$user->getUID() : '';
            $displayName = method_exists($user, 'getDisplayName') ? (string)$user->getDisplayName() : '';

            return [
                'userId' => $userId,
                'label' => $displayName !== '' && $displayName !== $userId
                    ? sprintf('%s (%s)', $displayName, $userId)
                    : $userId,
            ];
        }, $users);

        usort($result, static fn(array $a, array $b): int => strcasecmp((string)$a['label'], (string)$b['label']));
        return array_values(array_filter($result, static fn(array $entry): bool => (string)($entry['userId'] ?? '') !== ''));
    }

    private function normalizeCurrencyCode(?string $value): string {
        $normalized = strtoupper(trim((string)($value ?? '')));
        return $normalized !== '' ? $normalized : 'EUR';
    }
}
