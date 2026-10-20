<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

/**
 * Contrat commun des exemples de la documentation.
 *
 * Chaque exemple de `docs_src/` décrit une application (`app()`) et les
 * scénarios qui prouvent son comportement (`scenarios()`). Le cours s'appuie
 * sur ces fichiers et la suite de tests les exécute tels quels : la
 * documentation et le code ne peuvent pas diverger.
 *
 * Un scénario est un tableau :
 *
 *     [
 *         'method'   => 'GET',
 *         'uri'      => '/items/7?limit=2',
 *         'options'  => ['headers' => [...], 'json' => [...]],
 *         'status'   => 200,          // statut attendu
 *         'contains' => 'Bonjour',    // fragment attendu dans le corps
 *         'json'     => ['id' => 7],  // corps JSON attendu
 *     ]
 */
abstract class DocsExample
{
    /**
     * Construit l'application de l'exemple.
     */
    abstract public static function app(): EorbahAPI;

    /**
     * Scénarios exécutés par la suite de tests.
     *
     * @return list<array{method: string, uri: string, options?: array, status?: int, contains?: string, json?: array, headers?: array<string, string>}>
     */
    abstract public static function scenarios(): array;

    /**
     * Construit un scénario (sucre syntaxique pour `scenarios()`).
     */
    protected static function scenario(
        string $method,
        string $uri,
        array $options = [],
        int $status = 200,
        ?string $contains = null,
        ?array $json = null,
        ?array $headers = null
    ): array {
        $scenario = [
            'method' => $method,
            'uri' => $uri,
            'options' => $options,
            'status' => $status,
        ];

        if ($contains !== null) {
            $scenario['contains'] = $contains;
        }

        if ($json !== null) {
            $scenario['json'] = $json;
        }

        if ($headers !== null) {
            $scenario['headers'] = $headers;
        }

        return $scenario;
    }
}
