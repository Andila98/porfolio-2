<?php

declare(strict_types=1);

namespace App\Tips;

use PDO;
use PDOException;

/**
 * Append-only store for tip events. There is deliberately no update or delete
 * method: the database triggers would reject them anyway.
 */
final class Ledger
{
    public const FINAL_EVENTS = ['COMPLETED', 'CANCELLED', 'FAILED'];

    private const COLUMNS = [
        'tip_id', 'event', 'amount', 'phone_hash', 'phone_last3', 'merchant_request_id',
        'checkout_request_id', 'mpesa_receipt', 'result_code', 'result_desc', 'source',
        'environment', 'dedupe_key', 'raw_payload',
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Appends one event. Returns false when the row's dedupe_key already
     * exists, i.e. the tip was already settled.
     *
     * @param array<string, mixed> $row
     */
    public function append(array $row): bool
    {
        $values = [];
        foreach (self::COLUMNS as $column) {
            $values[] = $row[$column] ?? null;
        }
        $sql = sprintf(
            'INSERT INTO tip_ledger (%s) VALUES (%s)',
            implode(', ', self::COLUMNS),
            implode(', ', array_fill(0, count(self::COLUMNS), '?')),
        );
        try {
            $this->db->prepare($sql)->execute($values);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && !empty($row['dedupe_key'])) {
                return false;
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null The most recent event for a tip. */
    public function latest(string $tipId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tip_ledger WHERE tip_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$tipId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null The most recent event carrying this checkout id. */
    public function findByCheckout(string $checkoutRequestId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tip_ledger WHERE checkout_request_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$checkoutRequestId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * The M-Pesa receipt of a successful tip. Usually on the COMPLETED row, but
     * when reconcile settled the tip first (STK Query returns no receipt), the
     * real callback carrying it arrives later as a DUPLICATE_CALLBACK row.
     */
    public function receipt(string $tipId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT mpesa_receipt FROM tip_ledger
             WHERE tip_id = ? AND result_code = 0 AND mpesa_receipt IS NOT NULL
               AND event IN ('COMPLETED', 'DUPLICATE_CALLBACK')
             ORDER BY id LIMIT 1"
        );
        $stmt->execute([$tipId]);
        $receipt = $stmt->fetchColumn();
        return $receipt === false ? null : (string) $receipt;
    }

    /** @return list<array<string, mixed>> Tips still waiting on Safaricom after $seconds. */
    public function pendingOlderThan(int $seconds): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.* FROM tip_ledger l
             JOIN (SELECT tip_id, MAX(id) AS id FROM tip_ledger GROUP BY tip_id) latest ON latest.id = l.id
             WHERE l.event = 'SENT' AND l.created_at < (NOW(3) - INTERVAL ? SECOND)
             ORDER BY l.id"
        );
        $stmt->execute([$seconds]);
        return $stmt->fetchAll();
    }

    /**
     * One row per tip, newest first: its latest event plus its settled status
     * (final_event) and receipt, which a later DUPLICATE_CALLBACK must not hide.
     *
     * @return list<array<string, mixed>>
     */
    public function tips(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, first.created_at AS started_at,
                (SELECT f.event FROM tip_ledger f
                  WHERE f.tip_id = l.tip_id AND f.event IN ('COMPLETED', 'CANCELLED', 'FAILED')
                  ORDER BY f.id LIMIT 1) AS final_event,
                (SELECT r.mpesa_receipt FROM tip_ledger r
                  WHERE r.tip_id = l.tip_id AND r.result_code = 0 AND r.mpesa_receipt IS NOT NULL
                    AND r.event IN ('COMPLETED', 'DUPLICATE_CALLBACK')
                  ORDER BY r.id LIMIT 1) AS receipt
             FROM tip_ledger l
             JOIN (SELECT tip_id, MAX(id) AS last_id, MIN(id) AS first_id FROM tip_ledger GROUP BY tip_id) t ON t.last_id = l.id
             JOIN tip_ledger first ON first.id = t.first_id
             ORDER BY l.id DESC LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> Every event of one tip, oldest first. */
    public function events(string $tipId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tip_ledger WHERE tip_id = ? ORDER BY id');
        $stmt->execute([$tipId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> Every event, oldest first (CSV export). */
    public function allEvents(): array
    {
        return $this->db->query('SELECT * FROM tip_ledger ORDER BY id')->fetchAll();
    }

    /**
     * Completed tips per month. Only the production environment counts:
     * sandbox and fake rows are test money.
     *
     * @return list<array{month: string, tips: int, total: int}>
     */
    public function monthlyTotals(string $environment = 'production'): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS tips, SUM(amount) AS total
             FROM tip_ledger WHERE event = 'COMPLETED' AND environment = ?
             GROUP BY month ORDER BY month DESC"
        );
        $stmt->execute([$environment]);
        return $stmt->fetchAll();
    }
}
