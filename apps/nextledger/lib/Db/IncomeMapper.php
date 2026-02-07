<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class IncomeMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_incomes', Income::class);
    }

    /**
     * @return Income[]
     */
    public function findByFiscalYearId(int $fiscalYearId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'fiscal_year_id',
                    $qb->createNamedParameter($fiscalYearId, IQueryBuilder::PARAM_INT)
                )
            )
            ->orderBy('booked_at', 'DESC');

        return $this->findEntities($qb);
    }

    public function findByInvoiceId(int $invoiceId): ?Income {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'invoice_id',
                    $qb->createNamedParameter($invoiceId, IQueryBuilder::PARAM_INT)
                )
            )
            ->setMaxResults(1);

        $items = $this->findEntities($qb);
        return $items[0] ?? null;
    }
}
