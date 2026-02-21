<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class FiscalYear extends Entity {
    public $id;
    public $companyId;
    public $name;
    public $dateStart;
    public $dateEnd;
    public $isActive;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('name', 'string');
        $this->addType('dateStart', 'integer');
        $this->addType('dateEnd', 'integer');
        $this->addType('isActive', 'boolean');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
