<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\IDBConnection;

class DocumentSettingMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_settings_documents', DocumentSetting::class);
    }
}
