<?php

namespace EorBah545\Eorbahapi\Security\OAuth2;

interface UserInterface
{
    public function getIdentifier(): string|int;
    public function getUsername(): string;
}