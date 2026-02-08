<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Income;
use OCA\NextLedger\Db\IncomeMapper;
use OCA\NextLedger\Db\Invoice;
use OCA\NextLedger\Db\InvoiceMapper;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCA\NextLedger\Service\EmailSettingsService;
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
use OCP\Mail\IMailer;

class InvoicesController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private InvoiceMapper $invoiceMapper,
        private IncomeMapper $incomeMapper,
        private FiscalYearMapper $fiscalYearMapper,
        private NumberGenerator $numberGenerator,
        private InvoicePdfService $invoicePdfService,
        private IMailer $mailer,
        private EmailSettingsService $emailSettingsService,
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
        ?string $invoiceType = null,
        ?int $relatedOfferId = null,
        ?int $servicePeriodStart = null,
        ?int $servicePeriodEnd = null,
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
        $invoice->setInvoiceType($invoiceType ?: 'standard');
        $invoice->setRelatedOfferId($relatedOfferId);
        $invoice->setServicePeriodStart($servicePeriodStart);
        $invoice->setServicePeriodEnd($servicePeriodEnd);
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
        ?string $invoiceType = null,
        ?int $relatedOfferId = null,
        ?int $servicePeriodStart = null,
        ?int $servicePeriodEnd = null,
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
        $resolvedType = $invoiceType;
        if ($resolvedType === null || $resolvedType === '') {
            $resolvedType = $invoice->getInvoiceType() ?? 'standard';
        }
        $invoice->setInvoiceType($resolvedType);
        $invoice->setRelatedOfferId($relatedOfferId ?? $invoice->getRelatedOfferId());
        $invoice->setServicePeriodStart($servicePeriodStart ?? $invoice->getServicePeriodStart());
        $invoice->setServicePeriodEnd($servicePeriodEnd ?? $invoice->getServicePeriodEnd());
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

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function sendEmail(string $id, ?array $to = null, ?string $subject = null, ?string $body = null): JSONResponse {
        $invoiceId = (int)$id;
        try {
            /** @var Invoice $invoice */
            $invoice = $this->invoiceMapper->find($invoiceId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Rechnung nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $params = $this->request->getParams();
        $to = $to ?? $params['to'] ?? [];
        $subject = $subject ?? $params['subject'] ?? '';
        $body = $body ?? $params['body'] ?? '';

        $recipients = $this->normalizeRecipients($to);
        if (empty($recipients)) {
            return new JSONResponse(['message' => 'Keine gültigen Empfänger gefunden.'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $result = $this->invoicePdfService->buildPdf($invoice->getId());
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => 'PDF konnte nicht erzeugt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        try {
            $this->sendWithAttachment($recipients, (string)$subject, (string)$body, $result['filename'], $result['content']);
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => 'E-Mail konnte nicht gesendet werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        return new JSONResponse(['status' => 'sent']);
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

    private function normalizeRecipients(mixed $value): array {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (!is_array($value)) {
            return [];
        }

        $recipients = [];
        foreach ($value as $entry) {
            $email = trim((string)$entry);
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }

        return array_values(array_unique($recipients));
    }

    private function sendWithAttachment(array $recipients, string $subject, string $body, string $filename, string $content): void {
        $message = $this->mailer->createMessage();
        $message->setTo($recipients);
        $message->setSubject($subject);
        $message->setPlainBody($body);

        $emails = $this->emailSettingsService->getEffectiveEmails();
        if (!empty($emails['fromEmail'])) {
            $message->setFrom([$emails['fromEmail']]);
        }
        if (!empty($emails['replyToEmail'])) {
            $message->setReplyTo([$emails['replyToEmail']]);
        }

        $tmpPath = $this->writeTempAttachment($filename, $content);
        try {
            $attachment = $this->mailer->createAttachmentFromPath($tmpPath);
            $message->attach($attachment);
            $this->mailer->send($message);
        } finally {
            @unlink($tmpPath);
        }
    }

    private function writeTempAttachment(string $filename, string $content): string {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: 'attachment.pdf';
        $tmpPath = sys_get_temp_dir() . '/' . uniqid('nextledger-', true) . '-' . $safeName;
        file_put_contents($tmpPath, $content);
        return $tmpPath;
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
