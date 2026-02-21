<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class CaseElement extends Entity {
    public $id;
    public $companyId;
    public $caseId;
    public $name;
    public $note;
    public $attachmentPath;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('caseId', 'integer');
        $this->addType('name', 'string');
        $this->addType('note', 'text');
        $this->addType('attachmentPath', 'string');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
