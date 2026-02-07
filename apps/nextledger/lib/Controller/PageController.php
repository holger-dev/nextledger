<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller {
    public function __construct(string $appName, IRequest $request) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        return new TemplateResponse($this->appName, 'index');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function spa(): TemplateResponse {
        return $this->index();
    }
}
