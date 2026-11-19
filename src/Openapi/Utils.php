<?php

namespace Eorbahapi\Openapi;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\EorbahAPIError;

/**
 * Point d'entrée fonctionnel du générateur OpenAPI.
 *
 *     $spec = Utils::get_openapi($app);                 // tableau
 *     $json = Utils::get_openapi_json($app, pretty: true); // JSON
 *
 * Préférez `$app->openapi()`, qui passe par le cache de l'application lorsque
 * celui-ci est activé ; `Utils` reste utile pour un script de génération hors
 * application (CI, build d'un fichier `openapi.json`).
 */
final class Utils
{
    /**
     * Spécification OpenAPI de l'application, sous forme de tableau.
     *
     * @param array<string, mixed> $options Options du générateur (voir OpenApiBuilder).
     * @return array<string, mixed>
     */
    public static function get_openapi(object $app, array $options = []): array
    {
        return self::builder($app, $options)->build();
    }

    /**
     * Spécification OpenAPI sérialisée en JSON.
     *
     * @param array<string, mixed> $options Options du générateur.
     */
    public static function get_openapi_json(object $app, array $options = [], bool $pretty = true): string
    {
        return (string) json_encode(
            self::get_openapi($app, $options),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | ($pretty ? JSON_PRETTY_PRINT : 0)
            | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Écrit la spécification dans un fichier (par exemple `public/openapi.json`).
     *
     * @param array<string, mixed> $options Options du générateur.
     * @return string Chemin du fichier écrit.
     */
    public static function write_openapi(object $app, string $path = 'openapi.json', array $options = []): string
    {
        $json = self::get_openapi_json($app, $options);

        $directory = dirname($path);

        if ($directory !== '' && !is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (@file_put_contents($path, $json) === false) {
            throw new EorbahAPIError("Impossible d'écrire le fichier OpenAPI [$path].");
        }

        return $path;
    }

    private static function builder(object $app, array $options): OpenApiBuilder
    {
        if (!$app instanceof EorbahAPI) {
            throw new EorbahAPIError(
                'Utils::get_openapi() attend une instance de [' . EorbahAPI::class . '], '
                . 'reçu [' . get_debug_type($app) . '].'
            );
        }

        return new OpenApiBuilder($app, $options);
    }
}
