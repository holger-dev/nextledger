<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class EmailSetting extends Entity {
    public $id;
    public $userId;
    public $companyId;
    public $mode;
    public $fromEmail;
    public $replyToEmail;
    public $providerId;
    public $serviceId;

    public function __construct() {
        $this->addType('userId', 'string');
        $this->addType('companyId', 'integer');
        $this->addType('mode', 'string');
        $this->addType('fromEmail', 'string');
        $this->addType('replyToEmail', 'string');
        $this->addType('providerId', 'string');
        $this->addType('serviceId', 'string');
    }
}
