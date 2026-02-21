<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class InvoiceItem extends Entity {
    public $id;
    public $companyId;
    public $invoiceId;
    public $productId;
    public $positionType;
    public $name;
    public $description;
    public $quantity;
    public $unitPriceCents;
    public $totalCents;
    public $createdAt;
    public $updatedAt;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('invoiceId', 'integer');
        $this->addType('productId', 'integer');
        $this->addType('positionType', 'string');
        $this->addType('name', 'string');
        $this->addType('description', 'text');
        $this->addType('quantity', 'integer');
        $this->addType('unitPriceCents', 'integer');
        $this->addType('totalCents', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
