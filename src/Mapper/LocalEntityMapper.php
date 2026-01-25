<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use LogicException;

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
            $configs[$config->getClassName()] = $config;
        }

        $this->userEntityConfigs = $configs;
    }

    /**
     * @param list<KeycloakCredential> $credentials
     */
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

    public function support(KeycloakUserInterface $localUser): bool
    {
        return isset($this->userEntityConfigs[$localUser::class]);
    }
}
