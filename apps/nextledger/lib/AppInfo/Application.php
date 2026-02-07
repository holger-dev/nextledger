<?php

declare(strict_types=1);

namespace OCA\NextLedger\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'nextledger';

    public function __construct() {
        parent::__construct(self::APP_ID);

        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    #[\Override]
    public function register(IRegistrationContext $context): void {
        // Register services, event listeners, and capabilities here when needed.
    }

    #[\Override]
    public function boot(IBootContext $context): void {
        // Keep empty for now; app scripts are loaded by templates.
    }
}
