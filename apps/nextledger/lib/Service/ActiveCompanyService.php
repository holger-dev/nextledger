<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use InvalidArgumentException;
use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\CompanyContext;
use OCA\NextLedger\Db\CompanyContextMapper;
use OCA\NextLedger\Db\CompanyMapper;
use OCP\IUserSession;

class ActiveCompanyService {
    public function __construct(
        private CompanyMapper $companyMapper,
        private CompanyContextMapper $companyContextMapper,
        private IUserSession $userSession,
    ) {}

    /**
     * @return Company[]
     */
    public function getCompanies(): array {
        $companies = $this->companyMapper->findAll();
        usort($companies, static fn(Company $a, Company $b) => strcasecmp((string)$a->getName(), (string)$b->getName()));
        return $companies;
    }

    public function getActiveCompanyId(): int {
        $defaultCompany = $this->ensureDefaultCompany();
        $userId = $this->getUserId();
        if ($userId === null) {
            return (int)$defaultCompany->getId();
        }

        $context = $this->companyContextMapper->findByUserId($userId);
        $activeCompanyId = (int)($context?->getActiveCompanyId() ?? 0);
        if ($activeCompanyId > 0) {
            foreach ($this->companyMapper->findAll() as $company) {
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
        foreach ($this->companyMapper->findAll() as $company) {
            if ((int)$company->getId() === $activeId) {
                return $company;
            }
        }

        return $this->ensureDefaultCompany();
    }

    public function setActiveCompanyId(int $companyId): Company {
        $target = null;
        foreach ($this->companyMapper->findAll() as $company) {
            if ((int)$company->getId() === $companyId) {
                $target = $company;
                break;
            }
        }
        if ($target === null) {
            throw new InvalidArgumentException('Firma nicht gefunden.');
        }

        $userId = $this->getUserId();
        if ($userId !== null) {
            $this->persistContext($userId, $companyId);
        }

        return $target;
    }

    private function ensureDefaultCompany(): Company {
        $companies = $this->companyMapper->findAll(1, 0);
        if (!empty($companies)) {
            /** @var Company $company */
            $company = $companies[0];
            return $company;
        }

        $company = new Company();
        $company->setName('Meine Firma');
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
}
