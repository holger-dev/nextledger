<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class CaseEntity extends Entity {
    public $id;
    public $customerId;
    public $name;
    public $description;
    public $caseNumber;
    public $deckLink;
    public $kollektivLink;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('customerId', 'integer');
        $this->addType('name', 'string');
        $this->addType('description', 'text');
        $this->addType('caseNumber', 'string');
        $this->addType('deckLink', 'string');
        $this->addType('kollektivLink', 'string');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
