<?php

namespace EorBah545\Eorbahapi\security\OAuth2;

interface UserInterface
{
    public function getIdentifier(): string|int;
    public function getUsername(): string;
}