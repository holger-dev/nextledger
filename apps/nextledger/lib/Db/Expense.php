<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Expense extends Entity {
    public $id;
    public $companyId;
    public $fiscalYearId;
    public $name;
    public $description;
    public $amountCents;
    public $bookedAt;
    public $attachmentPath;
    public $recurringInterval;
    public $recurringUntil;
    public $recurringParentId;
    public $lastRecurredAt;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('fiscalYearId', 'integer');
        $this->addType('name', 'string');
        $this->addType('description', 'text');
        $this->addType('amountCents', 'integer');
        $this->addType('bookedAt', 'integer');
        $this->addType('attachmentPath', 'string');
        $this->addType('recurringInterval', 'string');
        $this->addType('recurringUntil', 'integer');
        $this->addType('recurringParentId', 'integer');
        $this->addType('lastRecurredAt', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
