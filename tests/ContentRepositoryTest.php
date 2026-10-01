<?php

declare(strict_types=1);

namespace Tests;

use App\Content\CollectionSchema;
use App\Content\ContentRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentRepositoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pf-content-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/history', 0777, true);
        file_put_contents($this->dir . '/achievements.json', json_encode([
            ['id' => 'a', 'title' => 'First', 'date' => '2025-01', 'category' => 'award', 'hidden' => false],
            ['id' => 'b', 'title' => 'Second', 'date' => '2025-02', 'category' => 'award', 'hidden' => true],
        ]));
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob($this->dir . '/*') ?: [], glob($this->dir . '/history/*') ?: []) as $file) {
            is_file($file) && unlink($file);
        }
        @rmdir($this->dir . '/history');
        @rmdir($this->dir);
    }

    public function testHiddenRecordsAreFilteredUnlessRequested(): void
    {
        $repo = new ContentRepository($this->dir);
        self::assertCount(1, $repo->all('achievements'));
        self::assertCount(2, $repo->all('achievements', true));
        self::assertNull($repo->find('achievements', 'b'));
        self::assertNotNull($repo->find('achievements', 'b', true));
    }

    public function testUpsertWritesAtomicallyAndKeepsHistory(): void
    {
        $repo = new ContentRepository($this->dir);
        $repo->upsert('achievements', ['id' => 'c', 'title' => 'Third'], null);
        $repo->upsert('achievements', ['id' => 'a2', 'title' => 'Renamed'], 'a');

        $fresh = new ContentRepository($this->dir);
        self::assertSame(['a2', 'b', 'c'], array_column($fresh->all('achievements', true), 'id'));
        self::assertCount(2, $fresh->history('achievements'));
        self::assertSame([], glob($this->dir . '/*.tmp-*'));
    }

    public function testDuplicateKeyIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new ContentRepository($this->dir))->upsert('achievements', ['id' => 'b', 'title' => 'Clash'], 'a');
    }

    public function testMoveAndRestore(): void
    {
        $repo = new ContentRepository($this->dir);
        $repo->move('achievements', 'b', -1);
        self::assertSame(['b', 'a'], array_column($repo->all('achievements', true), 'id'));

        $history = $repo->history('achievements');
        $repo->restore('achievements', $history[0]);
        self::assertSame(['a', 'b'], array_column((new ContentRepository($this->dir))->all('achievements', true), 'id'));
    }

    public function testSeedCopiesMissingFilesAndNeverOverwrites(): void
    {
        $seed = $this->dir . '/seed';
        mkdir($seed);
        file_put_contents($seed . '/achievements.json', '[{"id":"seeded"}]');
        file_put_contents($seed . '/skills.json', '[{"id":"backend","label":"Backend"}]');

        $live = $this->dir . '/live';
        $repo = new ContentRepository($live);
        $repo->seedFrom($seed);
        self::assertSame('backend', $repo->find('skills', 'backend')['id'] ?? null);

        // An admin edit must survive a later seed run (e.g. the next deploy).
        $repo->upsert('skills', ['id' => 'backend', 'label' => 'Edited'], 'backend');
        (new ContentRepository($live))->seedFrom($seed);
        self::assertSame('Edited', (new ContentRepository($live))->find('skills', 'backend')['label']);

        array_map('unlink', array_merge(glob($seed . '/*') ?: [], glob($live . '/history/*') ?: [], glob($live . '/*.*') ?: []));
        @rmdir($seed);
        @rmdir($live . '/history');
        @rmdir($live);
    }

    public function testStaleInstanceDoesNotOverwriteAnotherWriter(): void
    {
        $tabA = new ContentRepository($this->dir);
        $tabA->all('achievements', true); // tab A loaded the page earlier
        (new ContentRepository($this->dir))->upsert('achievements', ['id' => 'from-b', 'title' => 'B'], null);

        $tabA->upsert('achievements', ['id' => 'from-a', 'title' => 'A'], null);
        $ids = array_column((new ContentRepository($this->dir))->all('achievements', true), 'id');
        self::assertContains('from-a', $ids);
        self::assertContains('from-b', $ids);
    }

    public function testSchemaValidation(): void
    {
        [$record, $errors] = CollectionSchema::fromInput('projects', [
            'slug' => 'Bad Slug',
            'title' => '',
            'summary' => 'x',
            'stack' => "PHP\n\n MySQL ",
            'repos' => "Backend | https://github.com/x/y\nnot a url",
            'live_url' => 'nope',
            'featured' => '1',
        ]);
        self::assertArrayHasKey('slug', $errors);
        self::assertArrayHasKey('title', $errors);
        self::assertArrayHasKey('repos', $errors);
        self::assertArrayHasKey('live_url', $errors);
        self::assertSame(['PHP', 'MySQL'], $record['stack']);
        self::assertTrue($record['featured']);
        self::assertFalse($record['hidden']);
    }
}
