<?php

namespace Eorbahapi\Contracts;

/**
 * Contrat d'une réponse HTTP, indépendant du SAPI.
 *
 * Il permet à un adaptateur de serveur de construire une réponse, de la donner
 * à l'application puis de l'envoyer avec son propre transport.
 */
interface ResponseInterface
{
    /** Statut HTTP courant. */
    public function status(): int;

    /** Change le statut et retourne la réponse. */
    public function setStatus(int $status): static;

    /** Corps (`string`, `array` ou `null`). */
    public function body(): string|array|null;

    /** Remplace le corps et retourne la réponse. */
    public function setBody(string|array|null $body): static;

    /** @return array<string, string> En-têtes de la réponse. */
    public function headers(): array;

    /** Valeur d'un en-tête (insensible à la casse), ou null. */
    public function header(string $name): ?string;

    /** Pose un en-tête et retourne la réponse. */
    public function setHeader(string $name, string $value): static;

    /** Supprime un en-tête. */
    public function removeHeader(string $name): void;

    /** Corps sous forme de chaîne (les tableaux sont encodés en JSON). */
    public function text(): string;

    /** Envoie la réponse au client (statut, en-têtes, corps). */
    public function send(): void;
}
