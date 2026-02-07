<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCA\NextLedger\Db\BaseMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CounterMapper extends BaseMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_counters', Counter::class);
    }

    /**
     * @throws MultipleObjectsReturnedException
     */
    public function findByKey(string $counterKey): ?Counter {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where(
                $qb->expr()->eq(
                    'counter_key',
                    $qb->createNamedParameter($counterKey, IQueryBuilder::PARAM_STR)
                )
            );

        try {
            /** @var Counter $counter */
            $counter = $this->findEntity($qb);
            return $counter;
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function increment(string $counterKey): Counter {
        $counter = $this->findByKey($counterKey);
        if ($counter) {
            $counter->setCounterValue((int)$counter->getCounterValue() + 1);
            $counter->setUpdatedAt(time());
            /** @var Counter $saved */
            $saved = $this->update($counter);
            return $saved;
        }

        $counter = new Counter();
        $counter->setCounterKey($counterKey);
        $counter->setCounterValue(1);
        $counter->setUpdatedAt(time());
        /** @var Counter $saved */
        $saved = $this->insert($counter);
        return $saved;
    }
}
