<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use LogicException;
use Override;

final readonly class LocalEntityMapper implements LocalKeycloakUserBridgeMapperInterface
{
    /** @var array<string, UserEntityConfig> */
    private array $userEntityConfigs;

    /**
     * @param iterable<UserEntityConfig> $userEntityConfigs
     */
    public function __construct(iterable $userEntityConfigs)
    {
        $configs = [];

        foreach ($userEntityConfigs as $config) {
            $className = str_replace('\\\\', '\\', $config->getClassName());

            $configs[$className] = $config;
        }

        $this->userEntityConfigs = $configs;
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserCreation(KeycloakUserInterface $localUser): CreateUserProfileDto
    {
        $userConfig = $this->userEntityConfigs[$localUser::class] ?? null;
        if ($userConfig === null) {
            throw new LogicException('No user entity configuration for ' . $localUser::class);
        }

        $dto = new CreateUserProfileDto(
            username: $localUser->getUsername(),
            email: $localUser->getEmail(),
            emailVerified: $localUser->isEmailVerified(),
            enabled: $localUser->isEnabled(),
            firstName: $localUser->getFirstName(),
            lastName: $localUser->getLastName(),
            realm: $userConfig->getRealm(),
        );

        return $dto;
    }

    #[Override]
    public function support(KeycloakUserInterface $localUser): bool
    {
        return isset($this->userEntityConfigs[$localUser::class]);
    }
}
