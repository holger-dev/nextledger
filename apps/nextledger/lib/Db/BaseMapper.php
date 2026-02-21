<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Shared mapper helpers for NextLedger.
 *
 * Adds a simple findAll method that is no longer provided by QBMapper in NC 32.
 */
abstract class BaseMapper extends QBMapper {
    /**
     * @return Entity[]
     * @throws Exception
     */
    public function findAll(int $limit = 0, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName);

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        return $this->findEntities($qb);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Entity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * @return Entity[]
     * @throws Exception
     */
    public function findAllByCompanyId(int $companyId, int $limit = 0, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->eq(
                        'company_id',
                        $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)
                    ),
                    $qb->expr()->isNull('company_id')
                )
            );

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        return $this->findEntities($qb);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function findByIdAndCompanyId(int $id, int $companyId): Entity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq(
                        'company_id',
                        $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)
                    ),
                    $qb->expr()->isNull('company_id')
                )
            );

        return $this->findEntity($qb);
    }
}
