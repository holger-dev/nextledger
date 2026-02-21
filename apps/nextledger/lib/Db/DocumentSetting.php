<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class DocumentSetting extends Entity {
    public $id;
    public $companyId;
    public $autoStorePdfs;
    public $keepPdfVersions;

    public function __construct() {
        $this->addType('companyId', 'integer');
        $this->addType('autoStorePdfs', 'boolean');
        $this->addType('keepPdfVersions', 'boolean');
    }
}
