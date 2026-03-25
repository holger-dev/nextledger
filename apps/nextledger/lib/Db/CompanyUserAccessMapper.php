<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CompanyUserAccessMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_company_user_access', CompanyUserAccess::class);
    }

    /**
     * @return CompanyUserAccess[]
     */
    public function findAllByUserId(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

        return $this->findEntities($qb);
    }

    /**
     * @return CompanyUserAccess[]
     */
    public function findAllByCompanyId(int $companyId, int $limit = 0, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('company_id', $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)));

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        return $this->findEntities($qb);
    }

    public function deleteByCompanyId(int $companyId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->tableName)
            ->where($qb->expr()->eq('company_id', $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
