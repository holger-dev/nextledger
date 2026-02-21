<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Texts extends Entity {
    public $id;
    public $companyId;
    public $invoiceGreeting;
    public $offerGreeting;
    public $footerText;
    public $offerClosingText;
    public $invoiceClosingText;
    public $offerEmailSubject;
    public $offerEmailBody;
    public $invoiceEmailSubject;
    public $invoiceEmailBody;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('invoiceGreeting', 'text');
        $this->addType('offerGreeting', 'text');
        $this->addType('footerText', 'text');
        $this->addType('offerClosingText', 'text');
        $this->addType('invoiceClosingText', 'text');
        $this->addType('offerEmailSubject', 'text');
        $this->addType('offerEmailBody', 'text');
        $this->addType('invoiceEmailSubject', 'text');
        $this->addType('invoiceEmailBody', 'text');
    }
}
