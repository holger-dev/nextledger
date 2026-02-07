<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class InvoiceMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_invoices', Invoice::class);
    }

    /**
     * @return Invoice[]
     */
    public function findByCaseId(int $caseId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'case_id',
                    $qb->createNamedParameter($caseId, IQueryBuilder::PARAM_INT)
                )
            )
            ->orderBy('issue_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @return Invoice[]
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
            ->orderBy('issue_date', 'DESC');

        return $this->findEntities($qb);
    }
}
