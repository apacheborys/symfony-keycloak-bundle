<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Resolver;

interface UserEntityIdentifierFieldResolverInterface
{
    /**
     * @param class-string $className
     */
    public function resolve(string $className): string;
}
