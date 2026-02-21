<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\FiscalYear;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FiscalYearsController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private FiscalYearMapper $fiscalYearMapper,
        private ActiveCompanyService $activeCompanyService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $items = $this->fiscalYearMapper->findAllByCompanyId($companyId);
        $data = array_map(fn(FiscalYear $year) => $this->entityToArray($year), $items);

        usort($data, static function (array $a, array $b): int {
            $dateA = (int)($a['dateStart'] ?? 0);
            $dateB = (int)($b['dateStart'] ?? 0);
            if ($dateA === $dateB) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            }
            return $dateB <=> $dateA;
        });

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        ?string $name = null,
        ?int $dateStart = null,
        ?int $dateEnd = null,
        ?bool $isActive = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $year = new FiscalYear();
        $year->setCompanyId($companyId);
        $year->setName($name);
        $year->setDateStart($dateStart);
        $year->setDateEnd($dateEnd);
        $year->setIsActive($isActive ?? false);
        $year->setCreatedAt(time());
        $year->setUpdatedAt(time());

        $saved = $this->fiscalYearMapper->insert($year);
        if ($saved->getIsActive()) {
            $this->fiscalYearMapper->deactivateAllExcept($saved->getId(), $companyId);
        }
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function update(
        string $id,
        ?string $name = null,
        ?int $dateStart = null,
        ?int $dateEnd = null,
        ?bool $isActive = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $yearId = (int)$id;
        try {
            /** @var FiscalYear $year */
            $year = $this->fiscalYearMapper->findByIdAndCompanyId($yearId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Wirtschaftsjahr nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $year->setCompanyId($companyId);
        $year->setName($name);
        $year->setDateStart($dateStart);
        $year->setDateEnd($dateEnd);
        if ($isActive !== null) {
            $year->setIsActive($isActive);
        }
        $year->setUpdatedAt(time());

        $saved = $this->fiscalYearMapper->update($year);
        if ($saved->getIsActive()) {
            $this->fiscalYearMapper->deactivateAllExcept($saved->getId(), $companyId);
        }
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $yearId = (int)$id;
        try {
            /** @var FiscalYear $year */
            $year = $this->fiscalYearMapper->findByIdAndCompanyId($yearId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Wirtschaftsjahr nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->fiscalYearMapper->delete($year);
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
