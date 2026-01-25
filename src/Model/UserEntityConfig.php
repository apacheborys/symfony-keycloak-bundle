<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Model;

final readonly class UserEntityConfig
{
    public function __construct(
        private string $realm,
        private string $className,
    ) {
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
