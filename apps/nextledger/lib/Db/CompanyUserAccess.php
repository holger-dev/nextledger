<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class CompanyUserAccess extends Entity {
    public $id;
    public $companyId;
    public $userId;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('userId', 'string');
    }
}
