<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Invoice extends Entity {
    public $id;
    public $companyId;
    public $caseId;
    public $customerId;
    public $number;
    public $status;
    public $invoiceType;
    public $relatedOfferId;
    public $servicePeriodStart;
    public $servicePeriodEnd;
    public $issueDate;
    public $dueDate;
    public $greetingText;
    public $extraText;
    public $footerText;
    public $subtotalCents;
    public $taxCents;
    public $totalCents;
    public $taxRateBp;
    public $isSmallBusiness;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('caseId', 'integer');
        $this->addType('customerId', 'integer');
        $this->addType('number', 'string');
        $this->addType('status', 'string');
        $this->addType('invoiceType', 'string');
        $this->addType('relatedOfferId', 'integer');
        $this->addType('servicePeriodStart', 'integer');
        $this->addType('servicePeriodEnd', 'integer');
        $this->addType('issueDate', 'integer');
        $this->addType('dueDate', 'integer');
        $this->addType('greetingText', 'text');
        $this->addType('extraText', 'text');
        $this->addType('footerText', 'text');
        $this->addType('subtotalCents', 'integer');
        $this->addType('taxCents', 'integer');
        $this->addType('totalCents', 'integer');
        $this->addType('taxRateBp', 'integer');
        $this->addType('isSmallBusiness', 'boolean');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
