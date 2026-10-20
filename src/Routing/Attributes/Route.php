<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;
use Eorbahapi\Routing\Route as BaseRoute;

/**
 * Déclare une méthode de contrôleur comme route HTTP.
 *
 *     #[Route('/login', methods: ['GET', 'POST'])]
 *     public function login() { ... }
 *
 * Les attributs spécialisés ([Get], [Post], [Put], [Patch], [Delete], [Head],
 * [Options]) étendent cette classe et fixent la méthode HTTP.
 *
 * Les middlewares de route ne passent plus par cet attribut : utilisez
 * `#[Middleware(MaClasse::class, args: [...])]` sur la même méthode (ou sur la
 * classe entière). Le paramètre `middlewares` reste accepté pour compatibilité.
 *
 * Cette classe étend `Eorbahapi\Routing\Route` : les contrôleurs utilisant
 * encore l'ancien namespace `Eorbahapi\Routing\{Get,Post,...}` sont détectés de
 * la même façon par le scanner de contrôleurs.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Route extends BaseRoute {}
