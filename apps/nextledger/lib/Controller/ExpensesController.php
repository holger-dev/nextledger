<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Expense;
use OCA\NextLedger\Db\ExpenseMapper;
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
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(string $fiscalYearId): JSONResponse {
        $yearId = (int)$fiscalYearId;
        $items = $this->expenseMapper->findByFiscalYearId($yearId);
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
        $expense = new Expense();
        $expense->setFiscalYearId((int)$fiscalYearId);
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
        $expenseId = (int)$id;
        try {
            /** @var Expense $expense */
            $expense = $this->expenseMapper->find($expenseId);
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
        $expenseId = (int)$id;
        try {
            /** @var Expense $expense */
            $expense = $this->expenseMapper->find($expenseId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Ausgabe nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->expenseMapper->delete($expense);
        return new JSONResponse(['status' => 'ok']);
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
