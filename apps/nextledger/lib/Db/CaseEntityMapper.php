<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CaseEntityMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_cases', CaseEntity::class);
    }

    /**
     * @return CaseEntity[]
     */
    public function findByCustomerId(int $customerId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'customer_id',
                    $qb->createNamedParameter($customerId, IQueryBuilder::PARAM_INT)
                )
            )
            ->orderBy('name', 'ASC');

        return $this->findEntities($qb);
    }
}
