<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use InvalidArgumentException;
use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\CompanyContext;
use OCA\NextLedger\Db\CompanyContextMapper;
use OCA\NextLedger\Db\CompanyMapper;
use OCA\NextLedger\Db\CompanyUserAccess;
use OCA\NextLedger\Db\CompanyUserAccessMapper;
use OCP\IUserSession;
use OCP\IUserManager;

class ActiveCompanyService {
    public function __construct(
        private CompanyMapper $companyMapper,
        private CompanyContextMapper $companyContextMapper,
        private CompanyUserAccessMapper $companyUserAccessMapper,
        private IUserSession $userSession,
        private IUserManager $userManager,
    ) {}

    /**
     * @return Company[]
     */
    public function getCompanies(): array {
        $userId = $this->getUserId();
        $companies = $this->getAccessibleCompanies($userId);
        usort($companies, static function (Company $a, Company $b): int {
            $groupCompare = strcasecmp((string)$a->getGroupName(), (string)$b->getGroupName());
            if ($groupCompare !== 0) {
                return $groupCompare;
            }

            return strcasecmp((string)$a->getName(), (string)$b->getName());
        });

        return $companies;
    }

    public function getActiveCompanyId(): int {
        $userId = $this->getUserId();
        $companies = $this->getCompanies();
        if (empty($companies)) {
            if ($this->hasLegacyCompanies()) {
                return 0;
            }
            $companies = [$this->ensureDefaultCompany()];
        }

        $defaultCompany = $companies[0];
        if ($userId === null) {
            return (int)$defaultCompany->getId();
        }

        $context = $this->companyContextMapper->findByUserId($userId);
        $activeCompanyId = (int)($context?->getActiveCompanyId() ?? 0);
        if ($activeCompanyId > 0) {
            foreach ($companies as $company) {
                if ((int)$company->getId() === $activeCompanyId) {
                    return $activeCompanyId;
                }
            }
        }

        $this->persistContext($userId, (int)$defaultCompany->getId());
        return (int)$defaultCompany->getId();
    }

    public function getActiveCompany(): Company {
        $activeId = $this->getActiveCompanyId();
        if ($activeId <= 0) {
            throw new InvalidArgumentException('Keine aktive Firma verfügbar.');
        }
        foreach ($this->getCompanies() as $company) {
            if ((int)$company->getId() === $activeId) {
                return $company;
            }
        }

        return $this->ensureDefaultCompany();
    }

    public function setActiveCompanyId(int $companyId): Company {
        $target = $this->findAccessibleCompanyById($companyId);
        if ($target === null) {
            throw new InvalidArgumentException('Firma nicht gefunden.');
        }

        $userId = $this->getUserId();
        if ($userId !== null) {
            $this->persistContext($userId, $companyId);
        }

        return $target;
    }

    public function canAccessCompany(int $companyId): bool {
        return $this->findAccessibleCompanyById($companyId) !== null;
    }

    public function canManageCompanyUsers(int $companyId): bool {
        $company = $this->findAccessibleCompanyById($companyId);
        $userId = $this->getUserId();
        if ($company === null || $userId === null) {
            return false;
        }

        return (string)($company->getOwnerUserId() ?: '') === $userId;
    }

    /**
     * @return string[]
     */
    public function getSharedUserIds(int $companyId): array {
        if (!$this->canManageCompanyUsers($companyId)) {
            return [];
        }

        $shares = $this->companyUserAccessMapper->findAllByCompanyId($companyId);
        $userIds = array_map(
            static fn(CompanyUserAccess $share): string => trim((string)$share->getUserId()),
            $shares
        );
        $userIds = array_values(array_filter($userIds, static fn(string $value): bool => $value !== ''));
        sort($userIds, SORT_NATURAL | SORT_FLAG_CASE);

        return $userIds;
    }

    /**
     * @param string[] $userIds
     */
    public function saveSharedUserIds(int $companyId, array $userIds): void {
        if (!$this->canManageCompanyUsers($companyId)) {
            throw new InvalidArgumentException('Keine Berechtigung zum Verwalten der Freigaben.');
        }

        $company = $this->findAccessibleCompanyById($companyId);
        if ($company === null) {
            throw new InvalidArgumentException('Firma nicht gefunden.');
        }

        $ownerUserId = trim((string)($company->getOwnerUserId() ?: ''));
        $normalized = [];
        foreach ($userIds as $userId) {
            $value = trim((string)$userId);
            if (
                $value === ''
                || $value === $ownerUserId
                || !$this->userManager->userExists($value)
            ) {
                continue;
            }
            $normalized[$value] = true;
        }

        $existingShares = $this->companyUserAccessMapper->findAllByCompanyId($companyId);
        foreach ($existingShares as $share) {
            $sharedUserId = trim((string)$share->getUserId());
            if (!isset($normalized[$sharedUserId])) {
                $this->companyUserAccessMapper->delete($share);
                continue;
            }

            unset($normalized[$sharedUserId]);
        }

        foreach (array_keys($normalized) as $userId) {
            $share = new CompanyUserAccess();
            $share->setCompanyId($companyId);
            $share->setUserId($userId);
            $this->companyUserAccessMapper->insert($share);
        }
    }

    public function deleteCompanyShares(int $companyId): void {
        if ($companyId > 0) {
            $this->companyUserAccessMapper->deleteByCompanyId($companyId);
        }
    }

    /**
     * @return Company[]
     */
    public function getLegacyCompanies(): array {
        return $this->companyMapper->findLegacyCompanies();
    }

    public function hasLegacyCompanies(): bool {
        return !empty($this->getLegacyCompanies());
    }

    public function assignLegacyCompaniesToUser(string $targetUserId): void {
        $targetUserId = trim($targetUserId);
        if ($targetUserId === '' || !$this->userManager->userExists($targetUserId)) {
            throw new InvalidArgumentException('Benutzer nicht gefunden.');
        }

        foreach ($this->companyMapper->findLegacyCompanies() as $company) {
            $company->setOwnerUserId($targetUserId);
            $this->companyMapper->update($company);
        }

        $currentUserId = $this->getUserId();
        if ($currentUserId === $targetUserId) {
            $companies = $this->getCompanies();
            if (!empty($companies)) {
                $this->persistContext($currentUserId, (int)$companies[0]->getId());
            }
        }
    }

    private function ensureDefaultCompany(): Company {
        $userId = $this->getUserId();
        $companies = $this->getAccessibleCompanies($userId);
        if (!empty($companies)) {
            return $companies[0];
        }

        $company = new Company();
        $company->setName('Meine Firma');
        $company->setOwnerUserId($userId);

        /** @var Company $saved */
        $saved = $this->companyMapper->insert($company);
        return $saved;
    }

    private function persistContext(string $userId, int $companyId): void {
        $context = $this->companyContextMapper->findByUserId($userId);
        if ($context === null) {
            $context = new CompanyContext();
            $context->setUserId($userId);
            $context->setActiveCompanyId($companyId);
            $this->companyContextMapper->insert($context);
            return;
        }

        $context->setActiveCompanyId($companyId);
        $this->companyContextMapper->update($context);
    }

    private function getUserId(): ?string {
        $user = $this->userSession->getUser();
        if ($user && method_exists($user, 'getUID')) {
            return $user->getUID();
        }

        return null;
    }

    /**
     * @return Company[]
     */
    private function getAccessibleCompanies(?string $userId): array {
        if ($userId === null) {
            return [];
        }

        $sharedCompanyIds = [];
        foreach ($this->companyUserAccessMapper->findAllByUserId($userId) as $share) {
            $sharedCompanyIds[(int)$share->getCompanyId()] = true;
        }

        $result = [];
        foreach ($this->companyMapper->findAll() as $company) {
            $companyId = (int)$company->getId();
            $ownerUserId = trim((string)($company->getOwnerUserId() ?: ''));
            if ($ownerUserId === $userId || isset($sharedCompanyIds[$companyId])) {
                $result[] = $company;
            }
        }

        return $result;
    }

    private function findAccessibleCompanyById(int $companyId): ?Company {
        foreach ($this->getCompanies() as $company) {
            if ((int)$company->getId() === $companyId) {
                return $company;
            }
        }

        return null;
    }

}
