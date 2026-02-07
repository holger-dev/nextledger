<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Income;
use OCA\NextLedger\Db\IncomeMapper;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class IncomesController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private IncomeMapper $incomeMapper,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(string $fiscalYearId): JSONResponse {
        $yearId = (int)$fiscalYearId;
        $items = $this->incomeMapper->findByFiscalYearId($yearId);
        $data = array_map(fn(Income $income) => $this->entityToArray($income), $items);

        return new JSONResponse($data);
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
