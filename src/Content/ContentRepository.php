<?php

declare(strict_types=1);

namespace App\Content;

use RuntimeException;

/**
 * Reads and writes the JSON content files (storage/content by default).
 *
 * The files in /data are only the seed: they are copied in once when a
 * collection file is missing and never overwritten, so deploys (which reset
 * the git checkout) cannot undo edits made in /admin.
 *
 * Every change is a read-modify-write under an exclusive lock, written
 * atomically (temporary file + rename), and the previous version is copied
 * to history/ so any admin change can be undone.
 */
final class ContentRepository
{
    private const HISTORY_KEEP = 20;

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly string $dir)
    {
    }

    /** Copies any collection file that does not exist yet from $seedDir. Never overwrites. */
    public function seedFrom(string $seedDir): void
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new RuntimeException("Could not create content directory {$this->dir}");
        }
        foreach (array_keys(CollectionSchema::all()) as $collection) {
            $target = $this->path($collection);
            $seed = $seedDir . '/' . $collection . '.json';
            if (!is_file($target) && is_file($seed)) {
                copy($seed, $target);
            }
        }
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
        $records = self::records($this->read($collection));
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
            $this->mutate($collection, static fn (): array => $record);
            return;
        }
        $keyField = $schema['key'];
        $newKey = (string) $record[$keyField];

        $this->mutate($collection, static function (mixed $current) use ($record, $keyField, $newKey, $originalKey): array {
            $records = self::records($current);
            foreach ($records as $existing) {
                if (($existing[$keyField] ?? null) === $newKey && $newKey !== $originalKey) {
                    throw new RuntimeException("Another record already uses \"{$newKey}\".");
                }
            }
            if ($originalKey !== null) {
                foreach ($records as $i => $existing) {
                    if (($existing[$keyField] ?? null) === $originalKey) {
                        $records[$i] = $record;
                        return $records;
                    }
                }
            }
            $records[] = $record;
            return $records;
        });
    }

    public function delete(string $collection, string $key): void
    {
        $keyField = CollectionSchema::get($collection)['key'];
        $this->mutate($collection, static fn (mixed $current): array => array_values(array_filter(
            self::records($current),
            static fn (array $r): bool => ($r[$keyField] ?? null) !== $key,
        )));
    }

    /** Moves a record one position up (-1) or down (+1). */
    public function move(string $collection, string $key, int $direction): void
    {
        $keyField = CollectionSchema::get($collection)['key'];
        $this->mutate($collection, static function (mixed $current) use ($keyField, $key, $direction): array {
            $records = self::records($current);
            foreach ($records as $i => $record) {
                if (($record[$keyField] ?? null) === $key) {
                    $j = $i + ($direction < 0 ? -1 : 1);
                    if (isset($records[$j])) {
                        [$records[$i], $records[$j]] = [$records[$j], $records[$i]];
                    }
                    break;
                }
            }
            return $records;
        });
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
        $this->mutate($collection, static fn (): array => $data);
    }

    private function read(string $collection): mixed
    {
        if (!array_key_exists($collection, $this->cache)) {
            $this->cache[$collection] = $this->readFile($collection);
        }
        return $this->cache[$collection];
    }

    private function readFile(string $collection): mixed
    {
        $file = $this->path($collection);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return $data ?? [];
    }

    /**
     * Applies $change to the current file contents under an exclusive lock.
     * The file is re-read after locking, so two admin tabs cannot overwrite
     * each other's changes with stale data.
     *
     * @param callable(mixed): array<mixed> $change
     */
    private function mutate(string $collection, callable $change): void
    {
        $file = $this->path($collection);
        $lock = fopen($file . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException("Could not lock {$collection}.json");
        }
        try {
            $data = $change($this->readFile($collection));
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

            if (is_file($file)) {
                $this->keepHistory($collection, $file);
            }
            $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
            if (file_put_contents($tmp, $json) === false || !rename($tmp, $file)) {
                @unlink($tmp);
                throw new RuntimeException("Could not write {$collection}.json");
            }
            $this->cache[$collection] = $data;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function keepHistory(string $collection, string $file): void
    {
        $historyDir = $this->dir . '/history';
        if (!is_dir($historyDir)) {
            mkdir($historyDir, 0775, true);
        }
        // Microsecond timestamp keeps names unique and sortable.
        $stamp = (new \DateTimeImmutable())->format('Ymd-His-u');
        $target = sprintf('%s/%s-%s.json', $historyDir, $collection, $stamp);
        for ($n = 1; is_file($target); $n++) {
            $target = sprintf('%s/%s-%s-%d.json', $historyDir, $collection, $stamp, $n);
        }
        copy($file, $target);

        $files = glob($historyDir . '/' . $collection . '-*.json') ?: [];
        rsort($files);
        foreach (array_slice($files, self::HISTORY_KEEP) as $old) {
            @unlink($old);
        }
    }

    /** @return list<array<string, mixed>> */
    private static function records(mixed $data): array
    {
        return is_array($data) && array_is_list($data) ? $data : [];
    }

    private function path(string $collection): string
    {
        CollectionSchema::get($collection); // validates the name
        return $this->dir . '/' . $collection . '.json';
    }
}
