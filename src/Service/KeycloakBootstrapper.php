<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Service;

use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Entity\KeycloakBootstrapTarget;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use LogicException;

final readonly class KeycloakBootstrapper
{
    /** @var array<class-string, UserEntityConfig> */
    private array $userEntityConfigs;

    /**
     * @param iterable<UserEntityConfig> $userEntityConfigs
     */
    public function __construct(
        private KeycloakUserIdentifierAttributeServiceInterface $userIdentifierAttributeService,
        iterable $userEntityConfigs,
    ) {
        /** @var array<class-string, UserEntityConfig> $resolvedConfigs */
        $resolvedConfigs = [];
        foreach ($userEntityConfigs as $userEntityConfig) {
            /** @var class-string $className */
            $className = $userEntityConfig->getClassName();
            $resolvedConfigs[$className] = $userEntityConfig;
        }

        $this->userEntityConfigs = $resolvedConfigs;
    }

    /**
     * @param class-string $userEntityClass
     */
    public function ensureUserIdentifierAttribute(string $userEntityClass): void
    {
        $userEntityConfig = $this->getUserEntityConfig(userEntityClass: $userEntityClass);
        $this->userIdentifierAttributeService->ensureUserIdentifierAttribute(
            localUser: new KeycloakBootstrapTarget(userEntityConfig: $userEntityConfig),
            dto: $userEntityConfig->buildBootstrapUserIdentifierAttributeDto(),
        );
    }

    /**
     * @param class-string $userEntityClass
     */
    private function getUserEntityConfig(string $userEntityClass): UserEntityConfig
    {
        if (isset($this->userEntityConfigs[$userEntityClass])) {
            return $this->userEntityConfigs[$userEntityClass];
        }

        throw new LogicException(
            message: sprintf(
                'Keycloak bootstrapper does not have configuration for user entity "%s".',
                $userEntityClass,
            )
        );
    }
}
