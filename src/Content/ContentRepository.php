<?php

declare(strict_types=1);

namespace App\Content;

use RuntimeException;

/**
 * Reads and writes the JSON content files in /data.
 *
 * Writes are atomic (temporary file + rename under an exclusive lock), and the
 * previous version is copied to data/history/ so any admin change can be undone.
 */
final class ContentRepository
{
    private const HISTORY_KEEP = 20;

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly string $dir)
    {
    }

    /** @return array<string, mixed> */
    public function site(): array
    {
        $site = $this->read('site');
        return is_array($site) && !array_is_list($site) ? $site : [];
    }

    /**
     * All records of a collection, in file order.
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $collection, bool $includeHidden = false): array
    {
        $records = $this->read($collection);
        if (!is_array($records) || !array_is_list($records)) {
            return [];
        }
        if ($includeHidden) {
            return $records;
        }
        return array_values(array_filter($records, static fn (array $r): bool => empty($r['hidden'])));
    }

    /** @return array<string, mixed>|null */
    public function find(string $collection, string $key, bool $includeHidden = false): ?array
    {
        $keyField = CollectionSchema::get($collection)['key'];
        foreach ($this->all($collection, $includeHidden) as $record) {
            if (($record[$keyField] ?? null) === $key) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Inserts or replaces one record. $originalKey is the key before editing
     * (so a record can be renamed); null means "new record".
     *
     * @param array<string, mixed> $record
     */
    public function upsert(string $collection, array $record, ?string $originalKey): void
    {
        $schema = CollectionSchema::get($collection);
        if (!empty($schema['singleton'])) {
            $this->write($collection, $record);
            return;
        }
        $keyField = $schema['key'];
        $records = $this->all($collection, true);
        $newKey = (string) $record[$keyField];

        foreach ($records as $i => $existing) {
            if ($existing[$keyField] === $newKey && $newKey !== $originalKey) {
                throw new RuntimeException("Another record already uses \"{$newKey}\".");
            }
        }

        $replaced = false;
        if ($originalKey !== null) {
            foreach ($records as $i => $existing) {
                if ($existing[$keyField] === $originalKey) {
                    $records[$i] = $record;
                    $replaced = true;
                    break;
                }
            }
        }
        if (!$replaced) {
            $records[] = $record;
        }
        $this->write($collection, $records);
    }

    public function delete(string $collection, string $key): void
    {
        $keyField = CollectionSchema::get($collection)['key'];
        $records = array_values(array_filter(
            $this->all($collection, true),
            static fn (array $r): bool => ($r[$keyField] ?? null) !== $key,
        ));
        $this->write($collection, $records);
    }

    /** Moves a record one position up (-1) or down (+1). */
    public function move(string $collection, string $key, int $direction): void
    {
        $keyField = CollectionSchema::get($collection)['key'];
        $records = $this->all($collection, true);
        foreach ($records as $i => $record) {
            if (($record[$keyField] ?? null) === $key) {
                $j = $i + ($direction < 0 ? -1 : 1);
                if (isset($records[$j])) {
                    [$records[$i], $records[$j]] = [$records[$j], $records[$i]];
                    $this->write($collection, $records);
                }
                return;
            }
        }
    }

    /** @return list<string> History file names for a collection, newest first. */
    public function history(string $collection): array
    {
        $files = glob($this->dir . '/history/' . $collection . '-*.json') ?: [];
        rsort($files);
        return array_map('basename', $files);
    }

    public function restore(string $collection, string $historyFile): void
    {
        $file = $this->dir . '/history/' . basename($historyFile);
        if (!str_starts_with(basename($file), $collection . '-') || !is_file($file)) {
            throw new RuntimeException('History file not found.');
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException('History file is not valid JSON.');
        }
        $this->write($collection, $data);
    }

    private function read(string $collection): mixed
    {
        if (!array_key_exists($collection, $this->cache)) {
            $file = $this->path($collection);
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $this->cache[$collection] = $data ?? [];
        }
        return $this->cache[$collection];
    }

    private function write(string $collection, mixed $data): void
    {
        $file = $this->path($collection);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        $lock = fopen($file . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException("Could not lock {$collection}.json");
        }
        try {
            if (is_file($file)) {
                $historyDir = $this->dir . '/history';
                if (!is_dir($historyDir)) {
                    mkdir($historyDir, 0775, true);
                }
                // Microsecond timestamp keeps names unique and sortable (newest last).
                $stamp = (new \DateTimeImmutable())->format('Ymd-His-u');
                $target = sprintf('%s/%s-%s.json', $historyDir, $collection, $stamp);
                for ($n = 1; is_file($target); $n++) {
                    $target = sprintf('%s/%s-%s-%d.json', $historyDir, $collection, $stamp, $n);
                }
                copy($file, $target);
                $this->pruneHistory($collection);
            }
            $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
            if (file_put_contents($tmp, $json) === false || !rename($tmp, $file)) {
                @unlink($tmp);
                throw new RuntimeException("Could not write {$collection}.json");
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->cache[$collection] = $data;
    }

    private function pruneHistory(string $collection): void
    {
        $files = glob($this->dir . '/history/' . $collection . '-*.json') ?: [];
        rsort($files);
        foreach (array_slice($files, self::HISTORY_KEEP) as $old) {
            @unlink($old);
        }
    }

    private function path(string $collection): string
    {
        CollectionSchema::get($collection); // validates the name
        return $this->dir . '/' . $collection . '.json';
    }
}
