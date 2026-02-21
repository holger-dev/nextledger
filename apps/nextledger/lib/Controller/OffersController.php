<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\CaseEntityMapper;
use OCA\NextLedger\Db\CustomerMapper;
use OCA\NextLedger\Db\Offer;
use OCA\NextLedger\Db\OfferMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCA\NextLedger\Service\DocumentStorageService;
use OCA\NextLedger\Service\EmailSettingsService;
use OCA\NextLedger\Service\NumberGenerator;
use OCA\NextLedger\Service\OfferPdfService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\Mail\IMailer;

class OffersController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private OfferMapper $offerMapper,
        private NumberGenerator $numberGenerator,
        private OfferPdfService $offerPdfService,
        private IMailer $mailer,
        private EmailSettingsService $emailSettingsService,
        private CaseEntityMapper $caseMapper,
        private CustomerMapper $customerMapper,
        private ActiveCompanyService $activeCompanyService,
        private DocumentStorageService $documentStorageService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(?string $caseId = null, ?string $customerId = null): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();

        $caseFilter = null;
        if ($caseId !== null && $caseId !== '') {
            $caseFilter = (int)$caseId;
        }

        $customerFilter = null;
        if ($customerId !== null && $customerId !== '') {
            $customerFilter = (int)$customerId;
        }

        if ($caseFilter !== null) {
            $items = $this->offerMapper->findByCaseId($caseFilter, $companyId);
        } elseif ($customerFilter !== null) {
            $items = $this->offerMapper->findByCustomerId($customerFilter, $companyId);
        } else {
            $items = $this->offerMapper->findAllByCompanyId($companyId);
        }

        $data = array_map(fn(Offer $offer) => $this->entityToArray($offer), $items);
        usort($data, static fn(array $a, array $b) => ($b['issueDate'] ?? 0) <=> ($a['issueDate'] ?? 0));

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function show(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $offerId = (int)$id;
        try {
            /** @var Offer $offer */
            $offer = $this->offerMapper->findByIdAndCompanyId($offerId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Angebot nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse($this->entityToArray($offer));
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
        ?int $validUntil = null,
        ?string $greetingText = null,
        ?string $extraText = null,
        ?string $footerText = null,
        ?int $subtotalCents = null,
        ?int $taxCents = null,
        ?int $totalCents = null,
        ?int $taxRateBp = null,
        ?bool $isSmallBusiness = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        if ($caseId === null) {
            return new JSONResponse(['message' => 'Vorgang erforderlich.'], Http::STATUS_BAD_REQUEST);
        }
        if (!$this->caseExistsInCompany($caseId, $companyId)) {
            return new JSONResponse(['message' => 'Vorgang nicht gefunden.'], Http::STATUS_BAD_REQUEST);
        }
        if ($customerId !== null && !$this->customerExistsInCompany($customerId, $companyId)) {
            return new JSONResponse(['message' => 'Kunde nicht gefunden.'], Http::STATUS_BAD_REQUEST);
        }

        $offer = new Offer();
        $offer->setCompanyId($companyId);
        $offer->setCaseId($caseId);
        $offer->setCustomerId($customerId);
        $offer->setNumber($number ?: $this->numberGenerator->nextOfferNumber());
        $offer->setStatus($status ?: 'draft');
        $offer->setIssueDate($issueDate ?? time());
        $offer->setValidUntil($validUntil);
        $offer->setGreetingText($greetingText);
        $offer->setExtraText($extraText);
        $offer->setFooterText($footerText);
        $offer->setSubtotalCents($subtotalCents);
        $offer->setTaxCents($taxCents);
        $offer->setTotalCents($totalCents);
        $offer->setTaxRateBp($taxRateBp);
        $offer->setIsSmallBusiness($isSmallBusiness ?? false);
        $offer->setCreatedAt(time());
        $offer->setUpdatedAt(time());

        $saved = $this->offerMapper->insert($offer);
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
        ?int $validUntil = null,
        ?string $greetingText = null,
        ?string $extraText = null,
        ?string $footerText = null,
        ?int $subtotalCents = null,
        ?int $taxCents = null,
        ?int $totalCents = null,
        ?int $taxRateBp = null,
        ?bool $isSmallBusiness = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $offerId = (int)$id;
        try {
            /** @var Offer $offer */
            $offer = $this->offerMapper->findByIdAndCompanyId($offerId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Angebot nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        if ($caseId === null) {
            return new JSONResponse(['message' => 'Vorgang erforderlich.'], Http::STATUS_BAD_REQUEST);
        }
        if (!$this->caseExistsInCompany($caseId, $companyId)) {
            return new JSONResponse(['message' => 'Vorgang nicht gefunden.'], Http::STATUS_BAD_REQUEST);
        }
        if ($customerId !== null && !$this->customerExistsInCompany($customerId, $companyId)) {
            return new JSONResponse(['message' => 'Kunde nicht gefunden.'], Http::STATUS_BAD_REQUEST);
        }

        $offer->setCompanyId($companyId);
        $offer->setCaseId($caseId);
        $offer->setCustomerId($customerId);
        if ($number !== null && $number !== '') {
            $offer->setNumber($number);
        }
        $offer->setStatus($status);
        $offer->setIssueDate($issueDate);
        $offer->setValidUntil($validUntil);
        $offer->setGreetingText($greetingText);
        $offer->setExtraText($extraText);
        $offer->setFooterText($footerText);
        $offer->setSubtotalCents($subtotalCents);
        $offer->setTaxCents($taxCents);
        $offer->setTotalCents($totalCents);
        $offer->setTaxRateBp($taxRateBp);
        $offer->setIsSmallBusiness($isSmallBusiness ?? false);
        $offer->setUpdatedAt(time());

        $saved = $this->offerMapper->update($offer);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $offerId = (int)$id;
        try {
            /** @var Offer $offer */
            $offer = $this->offerMapper->findByIdAndCompanyId($offerId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Angebot nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->offerMapper->delete($offer);
        return new JSONResponse(['status' => 'ok']);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function pdf(string $id): Response {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $offerId = (int)$id;
        try {
            /** @var Offer $offer */
            $offer = $this->offerMapper->findByIdAndCompanyId($offerId, $companyId);
            $result = $this->offerPdfService->buildPdf($offerId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Angebot nicht gefunden.'], Http::STATUS_NOT_FOUND);
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => 'PDF konnte nicht erzeugt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $offer->setPdfGeneratedAt(time());
        $offer->setUpdatedAt(time());
        $this->offerMapper->update($offer);
        $this->documentStorageService->storeGeneratedPdf(
            (string)($offer->getNumber() ?: ('offer-' . $offer->getId())),
            $offer->getIssueDate(),
            $result['content']
        );

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
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $offerId = (int)$id;
        try {
            /** @var Offer $offer */
            $offer = $this->offerMapper->findByIdAndCompanyId($offerId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Angebot nicht gefunden.'], Http::STATUS_NOT_FOUND);
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
            $result = $this->offerPdfService->buildPdf($offer->getId());
            $this->documentStorageService->storeGeneratedPdf(
                (string)($offer->getNumber() ?: ('offer-' . $offer->getId())),
                $offer->getIssueDate(),
                $result['content']
            );
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

    private function caseExistsInCompany(int $caseId, int $companyId): bool {
        try {
            $this->caseMapper->findByIdAndCompanyId($caseId, $companyId);
            return true;
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return false;
        }
    }

    private function customerExistsInCompany(int $customerId, int $companyId): bool {
        try {
            $this->customerMapper->findByIdAndCompanyId($customerId, $companyId);
            return true;
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return false;
        }
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
