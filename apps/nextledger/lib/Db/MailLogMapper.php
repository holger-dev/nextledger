<?php

declare(strict_types=1);

namespace OCA\NextLedger\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MailLogMapper extends BaseMapper {
    private const KEEP_PER_COMPANY = 200;

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nl_mail_log', MailLog::class);
    }

    /**
     * @return MailLog[]
     */
    public function findRecentByCompanyId(int $companyId, int $limit = 100): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('company_id', $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit);

        return $this->findEntities($qb);
    }

    /**
     * Record a send attempt and prune old entries so the table stays small.
     */
    public function record(
        int $companyId,
        string $documentType,
        ?int $documentId,
        ?string $documentNumber,
        array $recipients,
        string $subject,
        string $status,
        ?string $error = null,
    ): MailLog {
        $entry = new MailLog();
        $entry->setCompanyId($companyId);
        $entry->setDocumentType($documentType);
        $entry->setDocumentId($documentId);
        $entry->setDocumentNumber(mb_substr((string)$documentNumber, 0, 64));
        $entry->setRecipients(mb_substr(implode(', ', $recipients), 0, 512));
        $entry->setSubject(mb_substr($subject, 0, 255));
        $entry->setStatus($status);
        $entry->setError($error !== null ? mb_substr($error, 0, 2000) : null);
        $entry->setCreatedAt(time());

        /** @var MailLog $saved */
        $saved = $this->insert($entry);
        $this->prune($companyId);
        return $saved;
    }

    private function prune(int $companyId): void {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')
                ->from($this->tableName)
                ->where($qb->expr()->eq('company_id', $qb->createNamedParameter($companyId, IQueryBuilder::PARAM_INT)))
                ->orderBy('created_at', 'DESC')
                ->addOrderBy('id', 'DESC')
                ->setFirstResult(self::KEEP_PER_COMPANY)
                ->setMaxResults(100);
            $result = $qb->executeQuery();
            $staleIds = array_map(static fn(array $row) => (int)$row['id'], $result->fetchAll());
            $result->closeCursor();

            if ($staleIds !== []) {
                $del = $this->db->getQueryBuilder();
                $del->delete($this->tableName)
                    ->where($del->expr()->in('id', $del->createNamedParameter($staleIds, IQueryBuilder::PARAM_INT_ARRAY)));
                $del->executeStatement();
            }
        } catch (\Throwable $e) {
            // pruning must never break the actual send flow
        }
    }
}
