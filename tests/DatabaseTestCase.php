<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Base class for tests that need MySQL/MariaDB. It rebuilds the schema from
 * database/schema.sql (exactly as phpMyAdmin would import it) before each test.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        try {
            $this->db = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_TEST_HOST'), getenv('DB_TEST_PORT'), getenv('DB_TEST_NAME')),
                (string) getenv('DB_TEST_USER'),
                (string) getenv('DB_TEST_PASS'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
            );
        } catch (Throwable $e) {
            // Locally a missing DB just skips; in CI it must fail, or the
            // payment tests could "pass" without ever running.
            if (getenv('CI') !== false && getenv('CI') !== '') {
                self::fail('Test database unreachable in CI: ' . $e->getMessage());
            }
            $this->markTestSkipped('No test database: ' . $e->getMessage());
        }

        // Same session time zone as the app (Database::connect).
        $this->db->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        $this->db->exec('DROP TABLE IF EXISTS tip_ledger, messages, cv_downloads, rate_limits');
        $sql = (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            // SIGNAL statements contain no semicolons, so splitting on ";" is safe for this file.
            $this->db->exec($statement);
        }
    }
}
