<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class TaxSetting extends Entity {
    public $id;
    public $companyId;
    public $vatRateBp;
    public $isSmallBusiness;
    public $smallBusinessNote;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('vatRateBp', 'integer');
        $this->addType('isSmallBusiness', 'boolean');
        $this->addType('smallBusinessNote', 'text');
    }
}
