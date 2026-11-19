<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Cache\ArrayCache;
use Eorbahapi\Cache\CacheManager;
use Eorbahapi\Cache\FileCache;
use Eorbahapi\Cache\NullCache;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Path;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/eorbah_cache_' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : @unlink($file);
        }
        @rmdir($dir);
    }

    #[Test]
    public function the_file_cache_stores_and_reads_values(): void
    {
        $cache = new FileCache($this->dir);

        $this->assertTrue($cache->set('clé', ['nom' => 'Oumar', 'nb' => 3]));
        $this->assertTrue($cache->has('clé'));
        $this->assertSame(['nom' => 'Oumar', 'nb' => 3], $cache->get('clé'));
        $this->assertNull($cache->get('inconnue'));

        // Une nouvelle instance relit le fichier : le cache survit au processus.
        $this->assertSame(['nom' => 'Oumar', 'nb' => 3], (new FileCache($this->dir))->get('clé'));

        $this->assertTrue($cache->delete('clé'));
        $this->assertFalse($cache->has('clé'));
    }

    #[Test]
    public function the_file_cache_expires_entries(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('clé', 'valeur');

        // Réécrit la charge utile avec une date d'expiration passée.
        file_put_contents(
            $cache->path('clé'),
            '<?php return ' . var_export(['value' => 'valeur', 'expires_at' => time() - 10], true) . ';'
        );

        $this->assertFalse($cache->has('clé'));
        $this->assertSame('défaut', $cache->get('clé', 'défaut'));
    }

    #[Test]
    public function the_file_cache_refuses_values_it_cannot_export(): void
    {
        $cache = new FileCache($this->dir);

        $this->assertFalse($cache->set('objet', new \DateTimeImmutable()));
        $this->assertFalse($cache->has('objet'));
    }

    #[Test]
    public function the_file_cache_supports_json_empty_objects(): void
    {
        $cache = new FileCache($this->dir);

        $this->assertTrue($cache->set('spec', ['paths' => new \stdClass()]));
        $this->assertInstanceOf(\stdClass::class, $cache->get('spec')['paths']);
    }

    #[Test]
    public function the_array_cache_remembers_a_value_once(): void
    {
        $cache = new ArrayCache();
        $calls = 0;

        $compute = function () use (&$calls): string {
            $calls++;

            return 'résultat';
        };

        $this->assertSame('résultat', $cache->remember('clé', $compute));
        $this->assertSame('résultat', $cache->remember('clé', $compute));
        $this->assertSame(1, $calls);

        $cache->delete('clé');
        $this->assertSame('résultat', $cache->remember('clé', $compute));
        $this->assertSame(2, $calls);
    }

    #[Test]
    public function the_null_cache_always_recomputes(): void
    {
        $cache = new NullCache();
        $calls = 0;

        $compute = function () use (&$calls): int {
            return ++$calls;
        };

        $this->assertSame(1, $cache->remember('clé', $compute));
        $this->assertSame(2, $cache->remember('clé', $compute));
        $this->assertFalse($cache->has('clé'));
        $this->assertSame('défaut', $cache->get('clé', 'défaut'));
    }

    #[Test]
    public function the_cache_manager_is_disabled_by_default(): void
    {
        $manager = CacheManager::disabled();

        $this->assertFalse($manager->enabled());
        $this->assertInstanceOf(NullCache::class, $manager->store());
    }

    #[Test]
    public function the_cache_manager_uses_a_file_store_when_enabled(): void
    {
        $manager = new CacheManager(true, null, $this->dir);

        $this->assertTrue($manager->enabled());
        $this->assertInstanceOf(FileCache::class, $manager->store());
        $this->assertTrue($manager->set('clé', 'valeur'));
        $this->assertSame('valeur', $manager->get('clé'));

        $manager->clear();
        $this->assertFalse($manager->has('clé'));
    }

    #[Test]
    public function the_application_exposes_its_cache_manager(): void
    {
        $app = new EorbahAPI(cache: true, cache_dir: $this->dir);

        $this->assertTrue($app->cache);
        $this->assertTrue($app->cache_manager()->enabled());
        $this->assertSame($this->dir, $app->cache_manager()->directory());
    }

    #[Test]
    public function routes_still_match_when_the_cache_is_enabled(): void
    {
        $app = new EorbahAPI(cache: true, cache_dir: $this->dir);
        $app->get('/users/{id}', fn (#[Path] int $id) => ['id' => $id]);

        $client = new TestClient($app);

        $this->assertSame(['id' => 7], $client->get('/users/7')->json());
        $this->assertSame(['id' => 8], $client->get('/users/8')->json());
        $this->assertSame(404, $client->get('/inconnu')->status());

        $this->assertTrue($app->cache_manager()->enabled());
    }
}
