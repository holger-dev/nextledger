<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Company extends Entity {
    public $id;
    public $name;
    public $ownerName;
    public $street;
    public $houseNumber;
    public $zip;
    public $city;
    public $email;
    public $phone;
    public $vatId;
    public $taxId;

    public function __construct() {
        $this->addType('name', 'string');
        $this->addType('ownerName', 'string');
        $this->addType('street', 'string');
        $this->addType('houseNumber', 'string');
        $this->addType('zip', 'string');
        $this->addType('city', 'string');
        $this->addType('email', 'string');
        $this->addType('phone', 'string');
        $this->addType('vatId', 'string');
        $this->addType('taxId', 'string');
    }
}
