<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\FiscalYearMapper;
use OCA\NextLedger\Db\Income;
use OCA\NextLedger\Db\IncomeMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class IncomesController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private IncomeMapper $incomeMapper,
        private FiscalYearMapper $fiscalYearMapper,
        private ActiveCompanyService $activeCompanyService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(string $fiscalYearId): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $yearId = (int)$fiscalYearId;
        if (!$this->fiscalYearExistsInCompany($yearId, $companyId)) {
            return new JSONResponse(['message' => 'Wirtschaftsjahr nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $items = $this->incomeMapper->findByFiscalYearId($yearId, $companyId);
        $data = array_map(fn(Income $income) => $this->entityToArray($income), $items);

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        string $fiscalYearId,
        ?string $name = null,
        ?string $description = null,
        ?int $amountCents = null,
        ?int $bookedAt = null,
        ?string $status = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $yearId = (int)$fiscalYearId;
        if (!$this->fiscalYearExistsInCompany($yearId, $companyId)) {
            return new JSONResponse(['message' => 'Wirtschaftsjahr nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $income = new Income();
        $income->setCompanyId($companyId);
        $income->setFiscalYearId($yearId);
        $income->setInvoiceId(null);
        $income->setName($name);
        $income->setDescription($description);
        $income->setAmountCents($amountCents);
        $income->setBookedAt($bookedAt);
        $income->setStatus($status);
        $income->setCreatedAt(time());
        $income->setUpdatedAt(time());

        $saved = $this->incomeMapper->insert($income);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function update(
        string $id,
        ?string $name = null,
        ?string $description = null,
        ?int $amountCents = null,
        ?int $bookedAt = null,
        ?string $status = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $incomeId = (int)$id;
        try {
            /** @var Income $income */
            $income = $this->incomeMapper->findByIdAndCompanyId($incomeId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Einnahme nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        if ($income->getInvoiceId() !== null) {
            return new JSONResponse(['message' => 'Einnahme stammt aus einer Rechnung.'], Http::STATUS_BAD_REQUEST);
        }

        $income->setName($name);
        $income->setDescription($description);
        $income->setAmountCents($amountCents);
        $income->setBookedAt($bookedAt);
        $income->setStatus($status);
        $income->setUpdatedAt(time());

        $saved = $this->incomeMapper->update($income);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $incomeId = (int)$id;
        try {
            /** @var Income $income */
            $income = $this->incomeMapper->findByIdAndCompanyId($incomeId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Einnahme nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        if ($income->getInvoiceId() !== null) {
            return new JSONResponse(['message' => 'Einnahme stammt aus einer Rechnung.'], Http::STATUS_BAD_REQUEST);
        }

        $this->incomeMapper->delete($income);
        return new JSONResponse(['status' => 'ok']);
    }

    private function fiscalYearExistsInCompany(int $yearId, int $companyId): bool {
        try {
            $this->fiscalYearMapper->findByIdAndCompanyId($yearId, $companyId);
            return true;
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return false;
        }
    }

    private function entityToArray(object $entity): array {
        if (method_exists($entity, 'jsonSerialize')) {
            /** @var array $data */
            $data = $entity->jsonSerialize();
            return $data;
        }

        return get_object_vars($entity);
    }
}
