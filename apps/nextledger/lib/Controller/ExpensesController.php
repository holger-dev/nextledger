<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Expense;
use OCA\NextLedger\Db\ExpenseMapper;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCA\NextLedger\Service\DocumentStorageService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ExpensesController extends ApiController {
    private const RECEIPT_MAX_BYTES = 10_000_000;
    private const RECEIPT_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'image/heic',
    ];

    public function __construct(
        string $appName,
        IRequest $request,
        private ExpenseMapper $expenseMapper,
        private FiscalYearMapper $fiscalYearMapper,
        private ActiveCompanyService $activeCompanyService,
        private DocumentStorageService $documentStorageService,
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
        ?string $recurringInterval = null,
        ?int $recurringUntil = null,
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
        $expense->setRecurringInterval($this->normalizeRecurringInterval($recurringInterval));
        $expense->setRecurringUntil($recurringUntil);
        $expense->setLastRecurredAt($bookedAt);
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
        ?string $recurringInterval = null,
        ?int $recurringUntil = null,
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
        $expense->setRecurringInterval($this->normalizeRecurringInterval($recurringInterval));
        $expense->setRecurringUntil($recurringUntil);
        $expense->setUpdatedAt(time());

        $saved = $this->expenseMapper->update($expense);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * Upload a receipt file for an expense (issue #16). Expects multipart "file".
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function uploadAttachment(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $expenseId = (int)$id;
        try {
            /** @var Expense $expense */
            $expense = $this->expenseMapper->findByIdAndCompanyId($expenseId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Ausgabe nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $upload = $this->request->getUploadedFile('file');
        if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['tmp_name'])) {
            return new JSONResponse(['message' => 'Keine Datei erhalten.'], Http::STATUS_BAD_REQUEST);
        }
        if ((int)($upload['size'] ?? 0) > self::RECEIPT_MAX_BYTES) {
            return new JSONResponse(['message' => 'Datei ist zu groß (max. 10 MB).'], Http::STATUS_BAD_REQUEST);
        }
        $mime = strtolower(trim((string)($upload['type'] ?? '')));
        if ($mime !== '' && !in_array($mime, self::RECEIPT_MIMES, true)) {
            return new JSONResponse(['message' => 'Nur PDF- oder Bilddateien sind als Beleg zulässig.'], Http::STATUS_BAD_REQUEST);
        }
        $content = @file_get_contents($upload['tmp_name']);
        if ($content === false || $content === '') {
            return new JSONResponse(['message' => 'Datei konnte nicht gelesen werden.'], Http::STATUS_BAD_REQUEST);
        }

        $path = $this->documentStorageService->storeExpenseReceipt(
            (string)($expense->getName() ?: ('Ausgabe-' . $expenseId)),
            $expense->getBookedAt(),
            (string)($upload['name'] ?? 'beleg'),
            $content
        );
        if ($path === null) {
            return new JSONResponse(['message' => 'Beleg konnte nicht gespeichert werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $expense->setAttachmentPath($path);
        $expense->setUpdatedAt(time());
        $saved = $this->expenseMapper->update($expense);

        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * Detach the receipt from an expense. The file itself stays in Nextcloud
     * Files — it belongs to the user, we only drop the link.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function deleteAttachment(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $expenseId = (int)$id;
        try {
            /** @var Expense $expense */
            $expense = $this->expenseMapper->findByIdAndCompanyId($expenseId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Ausgabe nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $expense->setAttachmentPath(null);
        $expense->setUpdatedAt(time());
        $saved = $this->expenseMapper->update($expense);

        return new JSONResponse($this->entityToArray($saved));
    }

    private function normalizeRecurringInterval(?string $value): string {
        $allowed = ['none', 'monthly', 'quarterly', 'yearly'];
        $normalized = strtolower(trim((string)($value ?? '')));
        return in_array($normalized, $allowed, true) ? $normalized : 'none';
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
