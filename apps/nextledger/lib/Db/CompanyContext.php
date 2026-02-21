<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class CompanyContext extends Entity {
    public $id;
    public $userId;
    public $activeCompanyId;

    public function __construct() {
        $this->addType('userId', 'string');
        $this->addType('activeCompanyId', 'integer');
    }
}
