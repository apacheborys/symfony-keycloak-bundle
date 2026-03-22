<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Model;

use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;

final readonly class UserEntityConfig
{
    public function __construct(
        private string $realm,
        private string $className,
        private string $rolePrefix = '',
        private string $roleSuffix = '',
        private string $mapper = LocalEntityMapper::class,
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

    public function getRolePrefix(): string
    {
        return $this->rolePrefix;
    }

    public function getRoleSuffix(): string
    {
        return $this->roleSuffix;
    }

    public function getMapper(): string
    {
        return $this->mapper;
    }
}
