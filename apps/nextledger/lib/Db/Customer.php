<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Customer extends Entity {
    public $id;
    public $company;
    public $contactName;
    public $street;
    public $houseNumber;
    public $zip;
    public $city;
    public $email;
    public $billingEmail;
    public $sendInvoiceToBillingEmail;
    public $sendInvoiceToContactEmail;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('company', 'string');
        $this->addType('contactName', 'string');
        $this->addType('street', 'string');
        $this->addType('houseNumber', 'string');
        $this->addType('zip', 'string');
        $this->addType('city', 'string');
        $this->addType('email', 'string');
        $this->addType('billingEmail', 'string');
        $this->addType('sendInvoiceToBillingEmail', 'boolean');
        $this->addType('sendInvoiceToContactEmail', 'boolean');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
