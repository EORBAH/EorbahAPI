<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Websockets\WebSocket;
use Eorbahapi\Websockets\WebSocketServer;

use Eorbahapi\Server\ESGIAppRunner;
use Eorbahapi\Adapter\Server\SwooleServerAdapter;
use Eorbahapi\Responses\FileResponse;
use Eorbahapi\Responses\HTMLResponse;

$app = new EorbahAPI(debug: true);

$app->get('/', fn(): HTMLResponse => new HTMLResponse(
<<<'HTML'
<!DOCTYPE html>
<html>
<body>
<script>
  const ws = new WebSocket("ws://localhost:8000/ws/echo");

  ws.onopen = () => {
    console.log("✅ Connecté");
    ws.send("Bonjour serveur !");
  };

  ws.onmessage = (event) => {
    console.log("📩 Reçu :", event.data);
  };

  ws.onerror = (err) => console.error("❌ Erreur :", err);
  ws.onclose = () => console.log("🔌 Fermé");
</script>
</body>
</html>

HTML
));
$app->get('/health', fn(): array => ['status' => 'ok_2']);

$app->websocket('/ws/echo', static function (WebSocket $websocket): void {
    $websocket->send('connecté au serveur d\'écho');

    while (($message = $websocket->receive()) !== null) {
        $websocket->send('écho : ' . $message);
    }
});

if (PHP_SAPI == "cli") {
    $arguments = $_SERVER['argv'] ?? [];

    $runner = new ESGIAppRunner();
    $runner->run(
        $app,
        '0.0.0.0',
        8000,
        true,
        new SwooleServerAdapter(workers: 4),
    );
    exit(0);
}

$app->run();
