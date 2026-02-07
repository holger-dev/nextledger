<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Expense extends Entity {
    public $id;
    public $fiscalYearId;
    public $name;
    public $description;
    public $amountCents;
    public $bookedAt;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('fiscalYearId', 'integer');
        $this->addType('name', 'string');
        $this->addType('description', 'text');
        $this->addType('amountCents', 'integer');
        $this->addType('bookedAt', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
