<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Examples\DocsExample;
use Eorbahapi\Examples\RunnersExample;
use Eorbahapi\Examples\TestingExample;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Exécute les exemples de la documentation (`docs_src/`).
 *
 * Chaque exemple fournit une application et des scénarios ; ce test garantit
 * que le code montré dans le cours fonctionne réellement.
 */
class ExamplesTest extends TestCase
{
    /**
     * @return list<class-string<DocsExample>>
     */
    private static function examples(): array
    {
        /** @var list<class-string<DocsExample>> $classes */
        $classes = require __DIR__ . '/../docs_src/registry.php';

        return $classes;
    }

    /**
     * @return array<string, array{0: class-string<DocsExample>}>
     */
    public static function exampleProvider(): array
    {
        $data = [];

        foreach (self::examples() as $class) {
            $short = substr((string) strrchr('\\' . $class, '\\'), 1);
            $data[$short] = [$class];
        }

        return $data;
    }

    #[Test]
    public function the_registry_only_contains_usable_examples(): void
    {
        foreach (self::examples() as $class) {
            $this->assertTrue(class_exists($class), "Classe d'exemple introuvable : $class");
            $this->assertTrue(is_subclass_of($class, DocsExample::class), "$class doit étendre DocsExample");
            $this->assertInstanceOf(EorbahAPI::class, $class::app(), "$class::app() doit retourner une application");
            $this->assertNotEmpty($class::scenarios(), "$class doit décrire au moins un scénario");
        }
    }

    #[Test]
    public function every_example_file_is_registered(): void
    {
        $registered = array_map(
            static fn (string $class): string => substr((string) strrchr('\\' . $class, '\\'), 1),
            self::examples()
        );

        foreach (glob(__DIR__ . '/../docs_src/*Example.php') ?: [] as $file) {
            $class = basename($file, '.php');

            if ($class === 'DocsExample') {
                continue;
            }

            $this->assertContains($class, $registered, "L'exemple $class n'est pas déclaré dans docs_src/registry.php");
        }
    }

    #[Test]
    #[DataProvider('exampleProvider')]
    public function the_example_behaves_as_documented(string $class): void
    {
        $client = new TestClient($class::app());

        foreach ($class::scenarios() as $index => $scenario) {
            $label = sprintf(
                '%s scénario #%d (%s %s)',
                $class,
                $index,
                $scenario['method'],
                $scenario['uri']
            );

            $response = $client->request($scenario['method'], $scenario['uri'], $scenario['options'] ?? []);

            $this->assertSame($scenario['status'] ?? 200, $response->status(), "$label : statut");

            if (isset($scenario['contains'])) {
                $this->assertStringContainsString($scenario['contains'], $response->text(), "$label : corps");
            }

            if (isset($scenario['json'])) {
                $this->assertSame($scenario['json'], $response->json(), "$label : JSON");
            }

            foreach ($scenario['headers'] ?? [] as $name => $value) {
                $actual = $response->header($name);

                $this->assertNotNull($actual, "$label : en-tête $name absent");
                $this->assertStringContainsString($value, (string) $actual, "$label : en-tête $name");
            }
        }
    }

    #[Test]
    public function the_testing_example_demonstrates_the_test_client(): void
    {
        $demo = TestingExample::demo();

        $this->assertSame(200, $demo['status']);
        $this->assertSame(['page' => 2], $demo['json']);
    }

    #[Test]
    public function the_runners_example_processes_requests_without_a_server(): void
    {
        $responses = RunnersExample::runner();

        $this->assertCount(2, $responses);
        $this->assertSame(200, $responses[0]->status);
        $this->assertSame(201, $responses[1]->status);
    }
}
