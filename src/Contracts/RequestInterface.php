<?php

namespace Eorbahapi\Contracts;

/**
 * Contrat d'une requête HTTP, indépendant du SAPI.
 *
 * Il reprend les accesseurs de `Eorbahapi\Request` sous une forme stable et
 * typée (`Eorbahapi\Adapter\Http\RequestAdapter` fait le pont). Les contrats
 * sont volontairement plus étroits que la classe concrète : ils figent ce dont
 * un serveur, un middleware compatible ou un runner a besoin.
 */
interface RequestInterface
{
    /** Méthode HTTP (`GET`, `POST`…). */
    public function getMethod(): string;

    /** URI complète, query comprise. */
    public function getUri(): string;

    /** Chemin seul, sans query. */
    public function getPath(): string;

    /** @return array<string, mixed> Paramètres de la chaîne de requête. */
    public function getQueryParams(): array;

    /** @return array<string, string> En-têtes de la requête. */
    public function getHeaders(): array;

    /** Valeur d'un en-tête (recherche insensible à la casse), ou null. */
    public function getHeader(string $name): ?string;

    /** Corps brut : tableau JSON décodé, chaîne, ou null. */
    public function getBody(): string|array|null;

    /** @return array<string, string> Paramètres extraits du chemin. */
    public function getPathParams(): array;

    /** @return array<string, mixed> Paramètres du serveur (`$_SERVER`). */
    public function getServerParams(): array;

    /** Attribut attaché à la requête (état applicatif, pas HTTP). */
    public function getAttribute(string $name, mixed $default = null): mixed;

    /** Copie de la requête avec un attribut supplémentaire. */
    public function withAttribute(string $name, mixed $value): static;
}
