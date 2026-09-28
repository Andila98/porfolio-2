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
        'dedupe_key', 'raw_payload',
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

    public function isSettled(string $tipId): bool
    {
        $stmt = $this->db->prepare(sprintf(
            "SELECT COUNT(*) FROM tip_ledger WHERE tip_id = ? AND event IN ('%s')",
            implode("','", self::FINAL_EVENTS),
        ));
        $stmt->execute([$tipId]);
        return (int) $stmt->fetchColumn() > 0;
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

    /** @return list<array<string, mixed>> One row per tip (its latest event), newest first. */
    public function tips(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, first.created_at AS started_at FROM tip_ledger l
             JOIN (SELECT tip_id, MAX(id) AS last_id, MIN(id) AS first_id FROM tip_ledger GROUP BY tip_id) t ON t.last_id = l.id
             JOIN tip_ledger first ON first.id = t.first_id
             ORDER BY l.id DESC LIMIT ? OFFSET ?'
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

    /** @return list<array{month: string, tips: int, total: int}> Completed tips per month. */
    public function monthlyTotals(): array
    {
        return $this->db->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS tips, SUM(amount) AS total
             FROM tip_ledger WHERE event = 'COMPLETED'
             GROUP BY month ORDER BY month DESC"
        )->fetchAll();
    }
}
