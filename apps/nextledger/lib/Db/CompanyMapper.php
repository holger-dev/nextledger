<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CompanyMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_settings_company', Company::class);
    }

    /**
     * @return Company[]
     */
    public function findLegacyCompanies(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->isNull('owner_user_id'),
                    $qb->expr()->eq('owner_user_id', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
                )
            );

        return $this->findEntities($qb);
    }
}
