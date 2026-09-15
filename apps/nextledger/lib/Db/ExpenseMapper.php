<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ExpenseMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_expenses', Expense::class);
    }

    /**
     * @return Expense[]
     */
    public function findByFiscalYearId(int $fiscalYearId, int $companyId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'fiscal_year_id',
                    $qb->createNamedParameter($fiscalYearId, IQueryBuilder::PARAM_INT)
                ),
            )
            ->andWhere($qb->expr()->eq('company_id', $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)))
            ->orderBy('booked_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * All expenses with an active recurrence, across all companies.
     * Used by the background job (issue #23).
     *
     * @return Expense[]
     */
    public function findRecurringTemplates(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->isNotNull('recurring_interval'))
            ->andWhere($qb->expr()->neq('recurring_interval', $qb->createNamedParameter('none')))
            ->andWhere($qb->expr()->isNull('recurring_parent_id'));

        return $this->findEntities($qb);
    }
}
