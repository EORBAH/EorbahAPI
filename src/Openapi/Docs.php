<?php

namespace Eorbahapi\Openapi;

/**
 * Pages HTML de documentation interactive.
 *
 * Les trois pages générées sont celles de FastAPI :
 *
 *  - Swagger UI (`get_swagger_ui_html`) : essayer les routes depuis le
 *    navigateur, avec le bouton « Authorize » pour les schémas de sécurité ;
 *  - ReDoc (`get_redoc_html`) : lecture confortable de la spécification ;
 *  - la page de retour OAuth2 (`get_swagger_ui_oauth2_redirect_html`), à
 *    déclarer comme `redirect_uri` d'un flux OAuth2 Swagger UI.
 *
 * Les fichiers JavaScript et CSS sont chargés depuis un CDN et peuvent être
 * remplacés par des options (`swagger_js_url`, `swagger_css_url`,
 * `swagger_preset_url`, `redoc_js_url`, `favicon_url`).
 *
 *     echo Docs::get_swagger_ui_html('/openapi.json', 'Mon API');
 */
final class Docs
{
    public const DEFAULT_SWAGGER_JS_URL = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js';

    public const DEFAULT_SWAGGER_CSS_URL = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css';

    public const DEFAULT_SWAGGER_PRESET_URL = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js';

    public const DEFAULT_REDOC_JS_URL = 'https://cdn.jsdelivr.net/npm/redoc@2/bundles/redoc.standalone.js';

    public const DEFAULT_FAVICON_URL = 'https://eorbah.github.io/EorbahAPI/img/favicon.png';

    /**
     * Page Swagger UI.
     *
     * @param string               $openapi_url          URL de la spécification JSON.
     * @param string|null          $title                Titre de l'onglet.
     * @param string|null          $oauth2_redirect_url  URL de la page de retour OAuth2.
     * @param array<string, mixed> $options              Surcharges (CDN, favicon…).
     */
    public static function get_swagger_ui_html(
        string $openapi_url = '/openapi.json',
        ?string $title = null,
        ?string $oauth2_redirect_url = null,
        array $options = []
    ): string {
        $title = $title ?? 'Documentation — ' . Constants::DEFAULT_TITLE;
        $bundleUrl = (string) ($options['swagger_js_url'] ?? self::DEFAULT_SWAGGER_JS_URL);
        $cssUrl = (string) ($options['swagger_css_url'] ?? self::DEFAULT_SWAGGER_CSS_URL);
        $presetUrl = (string) ($options['swagger_preset_url'] ?? self::DEFAULT_SWAGGER_PRESET_URL);
        $faviconUrl = (string) ($options['favicon_url'] ?? $options['swagger_favicon_url'] ?? self::DEFAULT_FAVICON_URL);

        $config = [
            'url' => $openapi_url,
            'dom_id' => '#swagger-ui',
            'deepLinking' => true,
            'layout' => 'StandaloneLayout',
        ];

        if ($oauth2_redirect_url !== null && $oauth2_redirect_url !== '') {
            $config['oauth2RedirectUrl'] = $oauth2_redirect_url;
        }

        if (isset($options['swagger_ui_init_oauth']) && is_array($options['swagger_ui_init_oauth'])) {
            $config['initOauth'] = $options['swagger_ui_init_oauth'];
        }

        // Paramètres libres de Swagger UI (`docExpansion`, `persistAuthorization`,
        // `defaultModelsExpandDepth`…) : `presets` est réservé au framework.
        foreach ((array) ($options['swagger_ui_parameters'] ?? []) as $name => $value) {
            if (is_string($name) && $name !== '' && $name !== 'presets') {
                $config[$name] = $value;
            }
        }

        $lines = [];
        foreach ($config as $name => $value) {
            $lines[] = self::js((string) $name) . ': ' . self::jsValue($value);
        }
        $lines[] = 'presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset]';

        $configJs = "{\n    " . implode(",\n    ", $lines) . "\n  }";

        return '<!doctype html>' . "\n"
            . '<html lang="fr">' . "\n"
            . '<head>' . "\n"
            . '  <meta charset="utf-8">' . "\n"
            . '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '  <title>' . self::html($title) . '</title>' . "\n"
            . '  <link rel="icon" href="' . self::html($faviconUrl) . '">' . "\n"
            . '  <link rel="stylesheet" href="' . self::html($cssUrl) . '">' . "\n"
            . '  <style>html{box-sizing:border-box;overflow-y:scroll}*,*:before,*:after{box-sizing:inherit}body{margin:0;background:#fafafa}</style>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '  <div id="swagger-ui"></div>' . "\n"
            . '  <script src="' . self::html($bundleUrl) . '" crossorigin></script>' . "\n"
            . '  <script src="' . self::html($presetUrl) . '" crossorigin></script>' . "\n"
            . '  <script>' . "\n"
            . '    window.onload = () => {' . "\n"
            . '      window.ui = SwaggerUIBundle(' . $configJs . ');' . "\n"
            . '    };' . "\n"
            . '  </script>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * Page ReDoc.
     *
     * @param array<string, mixed> $options Surcharges (CDN, favicon…).
     */
    public static function get_redoc_html(
        string $openapi_url = '/openapi.json',
        ?string $title = null,
        array $options = []
    ): string {
        $title = $title ?? 'Documentation — ' . Constants::DEFAULT_TITLE;
        $redocUrl = (string) ($options['redoc_js_url'] ?? self::DEFAULT_REDOC_JS_URL);
        $faviconUrl = (string) ($options['favicon_url'] ?? $options['redoc_favicon_url'] ?? self::DEFAULT_FAVICON_URL);

        return '<!doctype html>' . "\n"
            . '<html lang="fr">' . "\n"
            . '<head>' . "\n"
            . '  <meta charset="utf-8">' . "\n"
            . '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '  <title>' . self::html($title) . '</title>' . "\n"
            . '  <link rel="icon" href="' . self::html($faviconUrl) . '">' . "\n"
            . '  <style>body{margin:0;padding:0}</style>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '  <redoc spec-url="' . self::html($openapi_url) . '"></redoc>' . "\n"
            . '  <script src="' . self::html($redocUrl) . '"></script>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * Page de retour du flux OAuth2 de Swagger UI.
     *
     * Elle referme la fenêtre d'autorisation et transmet le code (ou le jeton)
     * à Swagger UI via `window.opener`.
     */
    public static function get_swagger_ui_oauth2_redirect_html(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Swagger UI — retour OAuth2</title>
</head>
<body>
  <script>
    'use strict';

    function run() {
      var oauth2 = window.opener.swaggerUIRedirectOauth2;
      var sentState = oauth2.state;
      var redirectUrl = oauth2.redirectUrl;
      var isValid, qp, arr;

      if (/code|token|error/.test(window.location.hash)) {
        qp = window.location.hash.substring(1);
      } else {
        qp = location.search.substring(1);
      }

      arr = qp.split('&');
      arr.forEach(function (value, index, array) {
        array[index] = '"' + value.replace('=', '":"') + '"';
      });
      qp = qp ? JSON.parse('{' + arr.join() + '}') : {};

      isValid = qp.state === sentState;

      if ((oauth2.auth.schema.get('flow') === 'accessCode' || oauth2.auth.schema.get('flow') === 'authorizationCode') && !oauth2.auth.code) {
        if (!isValid) {
          oauth2.errCb({
            authId: oauth2.auth.name,
            source: 'auth',
            level: 'warning',
            message: 'Authorization may be unsafe, passed state was changed in server. Passed state was not returned from auth server.'
          });
        }

        if (qp.code) {
          delete oauth2.state;
          oauth2.auth.code = qp.code;
          oauth2.callback({auth: oauth2.auth, redirectUrl: redirectUrl});
        } else {
          var oauthErrorMsg;

          if (qp.error) {
            oauthErrorMsg = '[' + qp.error + ']: ' + (qp.error_description ? qp.error_description + '. ' : 'no error_description provided.');
          }

          oauth2.errCb({
            authId: oauth2.auth.name,
            source: 'auth',
            level: 'error',
            message: oauthErrorMsg || '[Authorization failed]: no authorization code returned from auth server'
          });
        }
      } else {
        oauth2.callback({auth: oauth2.auth, token: qp, isValid: isValid, redirectUrl: redirectUrl});
      }

      window.close();
    }

    if (document.readyState !== 'loading') {
      run();
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        run();
      });
    }
  </script>
</body>
</html>
HTML;
    }

    private static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function js(string $value): string
    {
        return self::jsValue($value);
    }

    private static function jsValue(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
    }
}
