<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class FiscalYearMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_fiscal_years', FiscalYear::class);
    }

    public function deactivateAllExcept(?int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->tableName)
            ->set('is_active', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT));

        if ($id !== null) {
            $qb->where($qb->expr()->neq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        }

        $qb->executeStatement();
    }

    public function findActive(): ?FiscalYear {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'is_active',
                    $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
                )
            )
            ->setMaxResults(1);

        $items = $this->findEntities($qb);
        return $items[0] ?? null;
    }

    public function findByDate(int $timestamp): ?FiscalYear {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->lte(
                    'date_start',
                    $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)
                )
            )
            ->andWhere(
                $qb->expr()->gte(
                    'date_end',
                    $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)
                )
            )
            ->orderBy('date_start', 'DESC')
            ->setMaxResults(1);

        $items = $this->findEntities($qb);
        return $items[0] ?? null;
    }
}
