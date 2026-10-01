<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/** Fixed-window rate limiter backed by the rate_limits table. */
final class RateLimiter
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** Records a hit and returns false once the key exceeded $limit within the window. */
    public function attempt(string $key, int $limit, int $windowSeconds): bool
    {
        $window = intdiv(time(), $windowSeconds) * $windowSeconds;
        $bucket = Security::hash($key);

        $this->db->prepare(
            'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1'
        )->execute([$bucket, $window]);

        $stmt = $this->db->prepare('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?');
        $stmt->execute([$bucket, $window]);
        $hits = (int) $stmt->fetchColumn();

        // Opportunistic cleanup of old windows (1% of calls).
        if (random_int(1, 100) === 1) {
            $this->db->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([time() - 86400]);
        }
        return $hits <= $limit;
    }
}
