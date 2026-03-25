<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\FiscalYearMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCA\NextLedger\Service\GubPdfService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

class FiscalReportsController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private GubPdfService $gubPdfService,
        private FiscalYearMapper $fiscalYearMapper,
        private ActiveCompanyService $activeCompanyService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function gubPdf(string $id): Response {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $yearId = (int)$id;
        $includeDetails = $this->readIncludeDetails();
        try {
            $this->fiscalYearMapper->findByIdAndCompanyId($yearId, $companyId);
            $result = $this->gubPdfService->buildPdf($yearId, $includeDetails);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Wirtschaftsjahr nicht gefunden.'], Http::STATUS_NOT_FOUND);
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => 'PDF konnte nicht erzeugt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        return new DataDownloadResponse(
            $result['content'],
            $result['filename'],
            'application/pdf'
        );
    }

    private function readIncludeDetails(): bool {
        $rawValue = $this->request->getParam('includeDetails', '1');
        if (is_bool($rawValue)) {
            return $rawValue;
        }
        if (is_int($rawValue)) {
            return $rawValue !== 0;
        }
        if (!is_string($rawValue)) {
            return true;
        }

        return in_array(strtolower($rawValue), ['1', 'true', 'yes', 'on'], true);
    }
}
