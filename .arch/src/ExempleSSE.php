<?php

use Fastapi\FastAPI;
use function Fastapi\responses\HTMLResponse;
use function Fastapi\responses\StreamingResponse;

$app = new FastAPI();

function event_generator() {
    # """Générateur synchrone qui produit des messages SSE"""
    $counter = 1;
    while ($counter <= 10) {
        yield "data: Message numéro {$counter}";
        $counter += 1;
        sleep(1);
    }
}

$app->get("/events", function () {
    return StreamingResponse(
        event_generator(),
        "text/event-stream"
    );
});

$app->get("/", function () {
    return HTMLResponse('
    <!DOCTYPE html>
    <html>
    <body>
        <h1>Test SSE</h1>
        <ul id="messages"></ul>
        <script>
            const source = new EventSource("/events");
            const messages = document.getElementById("messages");
            source.onmessage = function(event) {
                const li = document.createElement("li");
                li.textContent = event.data;
                messages.appendChild(li);
            };
        </script>
    </body>
    </html>
    ');
});