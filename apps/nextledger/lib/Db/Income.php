<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Income extends Entity {
    public $id;
    public $fiscalYearId;
    public $invoiceId;
    public $name;
    public $amountCents;
    public $status;
    public $bookedAt;
    public $description;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('fiscalYearId', 'integer');
        $this->addType('invoiceId', 'integer');
        $this->addType('name', 'string');
        $this->addType('amountCents', 'integer');
        $this->addType('status', 'string');
        $this->addType('bookedAt', 'integer');
        $this->addType('description', 'text');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
