<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class OfferMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_offers', Offer::class);
    }

    /**
     * @return Offer[]
     */
    public function findByCaseId(int $caseId, int $companyId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'case_id',
                    $qb->createNamedParameter($caseId, IQueryBuilder::PARAM_INT)
                ),
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq(
                        'company_id',
                        $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)
                    ),
                    $qb->expr()->isNull('company_id')
                )
            )
            ->orderBy('issue_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @return Offer[]
     */
    public function findByCustomerId(int $customerId, int $companyId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'customer_id',
                    $qb->createNamedParameter($customerId, IQueryBuilder::PARAM_INT)
                ),
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq(
                        'company_id',
                        $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)
                    ),
                    $qb->expr()->isNull('company_id')
                )
            )
            ->orderBy('issue_date', 'DESC');

        return $this->findEntities($qb);
    }
}
