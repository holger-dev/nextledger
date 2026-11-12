<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class MailLog extends Entity {
    public $id;
    public $companyId;
    public $documentType;
    public $documentId;
    public $documentNumber;
    public $recipients;
    public $subject;
    public $status;
    public $error;
    public $createdAt;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('documentType', 'string');
        $this->addType('documentId', 'integer');
        $this->addType('documentNumber', 'string');
        $this->addType('recipients', 'string');
        $this->addType('subject', 'string');
        $this->addType('status', 'string');
        $this->addType('error', 'text');
        $this->addType('createdAt', 'integer');
    }
}
