<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Offer extends Entity {
    public $id;
    public $caseId;
    public $customerId;
    public $number;
    public $status;
    public $issueDate;
    public $validUntil;
    public $greetingText;
    public $extraText;
    public $footerText;
    public $subtotalCents;
    public $taxCents;
    public $totalCents;
    public $taxRateBp;
    public $isSmallBusiness;
    public $pdfGeneratedAt;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('caseId', 'integer');
        $this->addType('customerId', 'integer');
        $this->addType('number', 'string');
        $this->addType('status', 'string');
        $this->addType('issueDate', 'integer');
        $this->addType('validUntil', 'integer');
        $this->addType('greetingText', 'text');
        $this->addType('extraText', 'text');
        $this->addType('footerText', 'text');
        $this->addType('subtotalCents', 'integer');
        $this->addType('taxCents', 'integer');
        $this->addType('totalCents', 'integer');
        $this->addType('taxRateBp', 'integer');
        $this->addType('isSmallBusiness', 'boolean');
        $this->addType('pdfGeneratedAt', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
