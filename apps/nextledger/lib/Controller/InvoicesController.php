<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Income;
use OCA\NextLedger\Db\IncomeMapper;
use OCA\NextLedger\Db\Invoice;
use OCA\NextLedger\Db\InvoiceMapper;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCA\NextLedger\Service\InvoicePdfService;
use OCA\NextLedger\Service\NumberGenerator;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

class InvoicesController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private InvoiceMapper $invoiceMapper,
        private IncomeMapper $incomeMapper,
        private FiscalYearMapper $fiscalYearMapper,
        private NumberGenerator $numberGenerator,
        private InvoicePdfService $invoicePdfService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(?string $caseId = null, ?string $customerId = null): JSONResponse {
        $caseFilter = null;
        if ($caseId !== null && $caseId !== '') {
            $caseFilter = (int)$caseId;
        }

        $customerFilter = null;
        if ($customerId !== null && $customerId !== '') {
            $customerFilter = (int)$customerId;
        }

        if ($caseFilter !== null) {
            $items = $this->invoiceMapper->findByCaseId($caseFilter);
        } elseif ($customerFilter !== null) {
            $items = $this->invoiceMapper->findByCustomerId($customerFilter);
        } else {
            $items = $this->invoiceMapper->findAll();
        }

        $data = array_map(fn(Invoice $invoice) => $this->entityToArray($invoice), $items);
        usort($data, static fn(array $a, array $b) => ($b['issueDate'] ?? 0) <=> ($a['issueDate'] ?? 0));

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function show(string $id): JSONResponse {
        $invoiceId = (int)$id;
        try {
            /** @var Invoice $invoice */
            $invoice = $this->invoiceMapper->find($invoiceId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Rechnung nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse($this->entityToArray($invoice));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        ?int $caseId = null,
        ?int $customerId = null,
        ?string $number = null,
        ?string $status = null,
        ?int $issueDate = null,
        ?int $dueDate = null,
        ?string $greetingText = null,
        ?string $extraText = null,
        ?string $footerText = null,
        ?int $subtotalCents = null,
        ?int $taxCents = null,
        ?int $totalCents = null,
        ?int $taxRateBp = null,
        ?bool $isSmallBusiness = null,
    ): JSONResponse {
        $activeYear = $this->fiscalYearMapper->findActive();
        if (!$activeYear) {
            return new JSONResponse(
                ['message' => 'Kein aktives Wirtschaftsjahr vorhanden.'],
                Http::STATUS_BAD_REQUEST
            );
        }
        if ($caseId === null) {
            return new JSONResponse(['message' => 'Vorgang erforderlich.'], Http::STATUS_BAD_REQUEST);
        }

        $invoice = new Invoice();
        $invoice->setCaseId($caseId);
        $invoice->setCustomerId($customerId);
        $invoice->setNumber($number ?: $this->numberGenerator->nextInvoiceNumber());
        $invoice->setStatus($status ?: 'open');
        $invoice->setIssueDate($issueDate ?? time());
        $invoice->setDueDate($dueDate);
        $invoice->setGreetingText($greetingText);
        $invoice->setExtraText($extraText);
        $invoice->setFooterText($footerText);
        $invoice->setSubtotalCents($subtotalCents);
        $invoice->setTaxCents($taxCents);
        $invoice->setTotalCents($totalCents);
        $invoice->setTaxRateBp($taxRateBp);
        $invoice->setIsSmallBusiness($isSmallBusiness ?? false);
        $invoice->setCreatedAt(time());
        $invoice->setUpdatedAt(time());

        $saved = $this->invoiceMapper->insert($invoice);
        $this->syncIncome($saved);

        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function update(
        string $id,
        ?int $caseId = null,
        ?int $customerId = null,
        ?string $number = null,
        ?string $status = null,
        ?int $issueDate = null,
        ?int $dueDate = null,
        ?string $greetingText = null,
        ?string $extraText = null,
        ?string $footerText = null,
        ?int $subtotalCents = null,
        ?int $taxCents = null,
        ?int $totalCents = null,
        ?int $taxRateBp = null,
        ?bool $isSmallBusiness = null,
    ): JSONResponse {
        $invoiceId = (int)$id;
        try {
            /** @var Invoice $invoice */
            $invoice = $this->invoiceMapper->find($invoiceId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Rechnung nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        if ($caseId === null) {
            return new JSONResponse(['message' => 'Vorgang erforderlich.'], Http::STATUS_BAD_REQUEST);
        }

        $invoice->setCaseId($caseId);
        $invoice->setCustomerId($customerId);
        if ($number !== null && $number !== '') {
            $invoice->setNumber($number);
        }
        $invoice->setStatus($status);
        $invoice->setIssueDate($issueDate);
        $invoice->setDueDate($dueDate);
        $invoice->setGreetingText($greetingText);
        $invoice->setExtraText($extraText);
        $invoice->setFooterText($footerText);
        $invoice->setSubtotalCents($subtotalCents);
        $invoice->setTaxCents($taxCents);
        $invoice->setTotalCents($totalCents);
        $invoice->setTaxRateBp($taxRateBp);
        $invoice->setIsSmallBusiness($isSmallBusiness ?? false);
        $invoice->setUpdatedAt(time());

        $saved = $this->invoiceMapper->update($invoice);
        $this->syncIncome($saved);

        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $invoiceId = (int)$id;
        try {
            /** @var Invoice $invoice */
            $invoice = $this->invoiceMapper->find($invoiceId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Rechnung nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $income = $this->incomeMapper->findByInvoiceId($invoiceId);
        if ($income) {
            $this->incomeMapper->delete($income);
        }

        $this->invoiceMapper->delete($invoice);
        return new JSONResponse(['status' => 'ok']);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function pdf(string $id): Response {
        $invoiceId = (int)$id;
        try {
            $result = $this->invoicePdfService->buildPdf($invoiceId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Rechnung nicht gefunden.'], Http::STATUS_NOT_FOUND);
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => 'PDF konnte nicht erzeugt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        return new DataDownloadResponse(
            $result['content'],
            $result['filename'],
            'application/pdf'
        );
    }

    private function syncIncome(Invoice $invoice): void {
        $income = $this->incomeMapper->findByInvoiceId($invoice->getId());
        $year = null;
        if ($invoice->getIssueDate()) {
            $year = $this->fiscalYearMapper->findByDate($invoice->getIssueDate());
        }
        if (!$year) {
            $year = $this->fiscalYearMapper->findActive();
        }

        $description = $invoice->getNumber() ? 'Rechnung ' . $invoice->getNumber() : 'Rechnung';
        $status = $invoice->getStatus() ?: 'open';

        if ($income) {
            $income->setFiscalYearId($year?->getId());
            $income->setAmountCents($invoice->getTotalCents());
            $income->setStatus($status);
            $income->setBookedAt($invoice->getIssueDate());
            $income->setDescription($description);
            $income->setUpdatedAt(time());
            $this->incomeMapper->update($income);
            return;
        }

        $income = new Income();
        $income->setFiscalYearId($year?->getId());
        $income->setInvoiceId($invoice->getId());
        $income->setAmountCents($invoice->getTotalCents());
        $income->setStatus($status);
        $income->setBookedAt($invoice->getIssueDate());
        $income->setDescription($description);
        $income->setCreatedAt(time());
        $income->setUpdatedAt(time());
        $this->incomeMapper->insert($income);
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
