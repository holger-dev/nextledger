<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\CaseEntity;
use OCA\NextLedger\Db\CaseEntityMapper;
use OCA\NextLedger\Db\CounterMapper;
use OCA\NextLedger\Db\CustomerMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class CasesController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private CaseEntityMapper $caseMapper,
        private CounterMapper $counterMapper,
        private CustomerMapper $customerMapper,
        private ActiveCompanyService $activeCompanyService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(?string $customerId = null): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $filter = null;
        if ($customerId !== null && $customerId !== '') {
            $filter = (int)$customerId;
        }

        $items = $filter === null
            ? $this->caseMapper->findAllByCompanyId($companyId)
            : $this->caseMapper->findByCustomerId($filter, $companyId);

        $data = array_map(fn(CaseEntity $case) => $this->entityToArray($case), $items);
        usort($data, static fn(array $a, array $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        ?int $customerId = null,
        ?string $name = null,
        ?string $description = null,
        ?string $deckLink = null,
        ?string $kollektivLink = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        if ($customerId !== null && !$this->customerExistsInCompany($customerId, $companyId)) {
            return new JSONResponse(['message' => 'Kunde nicht gefunden.'], Http::STATUS_BAD_REQUEST);
        }

        $case = new CaseEntity();
        $case->setCompanyId($companyId);
        $case->setCustomerId($customerId);
        $case->setName($name);
        $case->setDescription($description);
        $case->setCaseNumber($this->generateCaseNumber($companyId));
        $case->setDeckLink($deckLink);
        $case->setKollektivLink($kollektivLink);
        $case->setCreatedAt(time());
        $case->setUpdatedAt(time());

        $saved = $this->caseMapper->insert($case);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function update(
        string $id,
        ?int $customerId = null,
        ?string $name = null,
        ?string $description = null,
        ?string $deckLink = null,
        ?string $kollektivLink = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $caseId = (int)$id;
        try {
            /** @var CaseEntity $case */
            $case = $this->caseMapper->findByIdAndCompanyId($caseId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Vorgang nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        if ($customerId !== null && !$this->customerExistsInCompany($customerId, $companyId)) {
            return new JSONResponse(['message' => 'Kunde nicht gefunden.'], Http::STATUS_BAD_REQUEST);
        }

        $case->setCompanyId($companyId);
        $case->setCustomerId($customerId);
        $case->setName($name);
        $case->setDescription($description);
        $case->setDeckLink($deckLink);
        $case->setKollektivLink($kollektivLink);
        $case->setUpdatedAt(time());

        $saved = $this->caseMapper->update($case);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $caseId = (int)$id;
        try {
            /** @var CaseEntity $case */
            $case = $this->caseMapper->findByIdAndCompanyId($caseId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Vorgang nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->caseMapper->delete($case);
        return new JSONResponse(['status' => 'ok']);
    }

    private function customerExistsInCompany(int $customerId, int $companyId): bool {
        try {
            $this->customerMapper->findByIdAndCompanyId($customerId, $companyId);
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

    private function generateCaseNumber(int $companyId): string {
        $dateKey = date('Ymd');
        $counterKey = sprintf('case-%d-%s', $companyId, $dateKey);
        $counter = $this->counterMapper->increment($counterKey);
        $running = str_pad((string)$counter->getCounterValue(), 3, '0', STR_PAD_LEFT);

        return sprintf('%s-%s', $dateKey, $running);
    }
}
