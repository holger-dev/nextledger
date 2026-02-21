<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\CaseElement;
use OCA\NextLedger\Db\CaseElementMapper;
use OCA\NextLedger\Db\CaseEntityMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class CaseElementsController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private CaseElementMapper $caseElementMapper,
        private CaseEntityMapper $caseMapper,
        private ActiveCompanyService $activeCompanyService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(string $caseId): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $caseKey = (int)$caseId;
        if (!$this->caseExistsInCompany($caseKey, $companyId)) {
            return new JSONResponse(['message' => 'Vorgang nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $items = $this->caseElementMapper->findByCaseId($caseKey, $companyId);
        $data = array_map(
            fn(CaseElement $element) => $this->entityToArray($element),
            $items
        );

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        string $caseId,
        ?string $name = null,
        ?string $note = null,
        ?string $attachmentPath = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $caseKey = (int)$caseId;
        if (!$this->caseExistsInCompany($caseKey, $companyId)) {
            return new JSONResponse(['message' => 'Vorgang nicht gefunden.'], Http::STATUS_BAD_REQUEST);
        }

        $element = new CaseElement();
        $element->setCompanyId($companyId);
        $element->setCaseId($caseKey);
        $element->setName($name);
        $element->setNote($note);
        $element->setAttachmentPath($attachmentPath);
        $element->setCreatedAt(time());
        $element->setUpdatedAt(time());

        $saved = $this->caseElementMapper->insert($element);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function update(
        string $id,
        ?string $name = null,
        ?string $note = null,
        ?string $attachmentPath = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $elementId = (int)$id;
        try {
            /** @var CaseElement $element */
            $element = $this->caseElementMapper->findByIdAndCompanyId($elementId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Element nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $element->setName($name);
        $element->setNote($note);
        $element->setAttachmentPath($attachmentPath);
        $element->setUpdatedAt(time());

        $saved = $this->caseElementMapper->update($element);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $elementId = (int)$id;
        try {
            /** @var CaseElement $element */
            $element = $this->caseElementMapper->findByIdAndCompanyId($elementId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Element nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->caseElementMapper->delete($element);
        return new JSONResponse(['status' => 'ok']);
    }

    private function caseExistsInCompany(int $caseId, int $companyId): bool {
        try {
            $this->caseMapper->findByIdAndCompanyId($caseId, $companyId);
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
