<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CompanyContextMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_company_context', CompanyContext::class);
    }

    public function findByUserId(string $userId): ?CompanyContext {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'user_id',
                    $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)
                )
            );

        try {
            /** @var CompanyContext $context */
            $context = $this->findEntity($qb);
            return $context;
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return null;
        }
    }
}
