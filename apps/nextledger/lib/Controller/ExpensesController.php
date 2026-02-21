<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Expense;
use OCA\NextLedger\Db\ExpenseMapper;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ExpensesController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private ExpenseMapper $expenseMapper,
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

        $items = $this->expenseMapper->findByFiscalYearId($yearId, $companyId);
        $data = array_map(fn(Expense $expense) => $this->entityToArray($expense), $items);

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
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $yearId = (int)$fiscalYearId;
        if (!$this->fiscalYearExistsInCompany($yearId, $companyId)) {
            return new JSONResponse(['message' => 'Wirtschaftsjahr nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $expense = new Expense();
        $expense->setCompanyId($companyId);
        $expense->setFiscalYearId($yearId);
        $expense->setName($name);
        $expense->setDescription($description);
        $expense->setAmountCents($amountCents);
        $expense->setBookedAt($bookedAt);
        $expense->setCreatedAt(time());
        $expense->setUpdatedAt(time());

        $saved = $this->expenseMapper->insert($expense);
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
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $expenseId = (int)$id;
        try {
            /** @var Expense $expense */
            $expense = $this->expenseMapper->findByIdAndCompanyId($expenseId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Ausgabe nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $expense->setName($name);
        $expense->setDescription($description);
        $expense->setAmountCents($amountCents);
        $expense->setBookedAt($bookedAt);
        $expense->setUpdatedAt(time());

        $saved = $this->expenseMapper->update($expense);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $expenseId = (int)$id;
        try {
            /** @var Expense $expense */
            $expense = $this->expenseMapper->findByIdAndCompanyId($expenseId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Ausgabe nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->expenseMapper->delete($expense);
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
