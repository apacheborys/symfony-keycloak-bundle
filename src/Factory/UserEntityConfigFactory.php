<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Factory;

use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;

final readonly class UserEntityConfigFactory
{
    /**
     * @param class-string $className
     * @param list<array{
     *  property: string,
     *  attribute_name: string|null,
     *  jwt_claim_name: string|null,
     *  create_if_missing: bool,
     *  required?: array{roles?: list<string>, scopes?: list<string>}|bool|null
     * }> $attributesMap
     */
    public function create(
        string $realm,
        string $className,
        bool $roleAllowCreation = false,
        string $rolePrefix = '',
        string $roleSuffix = '',
        string $mapper = LocalEntityMapper::class,
        array $attributesMap = [],
    ): UserEntityConfig {
        return new UserEntityConfig(
            realm: $realm,
            className: $className,
            roleAllowCreation: $roleAllowCreation,
            rolePrefix: $rolePrefix,
            roleSuffix: $roleSuffix,
            mapper: $mapper,
            attributesMap: $attributesMap,
        );
    }
}
