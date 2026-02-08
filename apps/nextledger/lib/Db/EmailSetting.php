<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class EmailSetting extends Entity {
    public $id;
    public $userId;
    public $mode;
    public $fromEmail;
    public $replyToEmail;

    public function __construct() {
        $this->addType('userId', 'string');
        $this->addType('mode', 'string');
        $this->addType('fromEmail', 'string');
        $this->addType('replyToEmail', 'string');
    }
}
