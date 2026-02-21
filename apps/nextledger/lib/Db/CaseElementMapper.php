<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CaseElementMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_case_elements', CaseElement::class);
    }

    /**
     * @return CaseElement[]
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
            ->orderBy('created_at', 'ASC');

        return $this->findEntities($qb);
    }
}
