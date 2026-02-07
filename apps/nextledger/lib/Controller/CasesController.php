<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\CaseEntity;
use OCA\NextLedger\Db\CaseEntityMapper;
use OCA\NextLedger\Db\CounterMapper;
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
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(?string $customerId = null): JSONResponse {
        $filter = null;
        if ($customerId !== null && $customerId !== '') {
            $filter = (int)$customerId;
        }

        $items = $filter === null
            ? $this->caseMapper->findAll()
            : $this->caseMapper->findByCustomerId($filter);

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
        $case = new CaseEntity();
        $case->setCustomerId($customerId);
        $case->setName($name);
        $case->setDescription($description);
        $case->setCaseNumber($this->generateCaseNumber());
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
        $caseId = (int)$id;
        try {
            /** @var CaseEntity $case */
            $case = $this->caseMapper->find($caseId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Vorgang nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

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
        $caseId = (int)$id;
        try {
            /** @var CaseEntity $case */
            $case = $this->caseMapper->find($caseId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Vorgang nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->caseMapper->delete($case);
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

    private function generateCaseNumber(): string {
        $dateKey = date('Ymd');
        $counterKey = sprintf('case-%s', $dateKey);
        $counter = $this->counterMapper->increment($counterKey);
        $running = str_pad((string)$counter->getCounterValue(), 3, '0', STR_PAD_LEFT);

        return sprintf('%s-%s', $dateKey, $running);
    }
}
