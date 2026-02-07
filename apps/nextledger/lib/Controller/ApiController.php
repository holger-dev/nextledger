<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCP\AppFramework\ApiController as BaseApiController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ApiController extends BaseApiController {
    public function __construct(string $appName, IRequest $request) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function health(): JSONResponse {
        return new JSONResponse([
            'status' => 'ok',
            'app' => $this->appName,
        ]);
    }
}
