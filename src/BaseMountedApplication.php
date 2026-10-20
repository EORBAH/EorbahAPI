<?php

namespace Eorbahapi;

use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Base des applications montées (`$app->mount('/prefix', $subApp)`).
 *
 * Une application montée est un handler de requête comme un autre : `handle()`
 * reçoit la requête (préfixe déjà retiré par l'application hôte), remplit la
 * réponse partagée dans `process()` et la retourne sans l'envoyer. `run()` reste
 * disponible pour un usage autonome (elle envoie la réponse).
 */
class BaseMountedApplication {
    public Request $request;
    public Response $response;

    /**
     * Permet à EorbahAPI d'injecter les instances Request/Response partagées.
     * Appelé automatiquement par mount().
     */
    public function setRequestResponse(Request $request, Response $response): void {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Usage autonome : traite la requête courante et envoie la réponse.
     */
    public function run(): void {
        $this->handle($this->request ?? new Request(), $this->response ?? new Response())->send();
    }

    /**
     * Point d'entrée principal appelé par mount() puis par le pipeline
     * d'EorbahAPI. Retourne la réponse sans l'envoyer.
     */
    public function handle(?Request $request = null, ?Response $response = null): Response {
        $this->setRequestResponse($request ?? new Request(), $response ?? new Response());

        $this->process();

        return $this->response;
    }

    /**
     * Traitement de la requête : à surcharger. Les sous-classes remplissent
     * `$this->response` (statut, en-têtes, corps).
     */
    public function process(): void {}
}
