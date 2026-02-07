<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class MiscSetting extends Entity {
    public $id;
    public $paymentTermsDays;
    public $bankName;
    public $iban;
    public $bic;
    public $accountHolder;

    public function __construct() {
        $this->addType('paymentTermsDays', 'integer');
        $this->addType('bankName', 'string');
        $this->addType('iban', 'string');
        $this->addType('bic', 'string');
        $this->addType('accountHolder', 'string');
    }
}
