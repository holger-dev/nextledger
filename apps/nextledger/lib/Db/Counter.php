<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;

class Counter extends Entity {
    public $id;
    public $counterKey;
    public $counterValue;
    public $updatedAt;

    public function __construct() {
        $this->addType('counterKey', 'string');
        $this->addType('counterValue', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
