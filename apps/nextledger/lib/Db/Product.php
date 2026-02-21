<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Product extends Entity {
    public $id;
    public $companyId;
    public $name;
    public $description;
    public $unitPriceCents;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('name', 'string');
        $this->addType('description', 'text');
        $this->addType('unitPriceCents', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
