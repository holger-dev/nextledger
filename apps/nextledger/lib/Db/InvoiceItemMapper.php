<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class InvoiceItemMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_invoice_items', InvoiceItem::class);
    }

    /**
     * @return InvoiceItem[]
     */
    public function findByInvoiceId(int $invoiceId, int $companyId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'invoice_id',
                    $qb->createNamedParameter($invoiceId, IQueryBuilder::PARAM_INT)
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
