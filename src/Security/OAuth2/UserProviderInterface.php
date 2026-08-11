<?php

namespace Eorbahapi\Security\OAuth2;

interface UserProviderInterface
{
    /**
     * Retourne un objet UserInterface si les identifiants sont valides, sinon null.
     */
    public function findUserByCredentials(string $username, string $password): ?UserInterface;
}