<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
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
    public function __construct(
        iterable $userEntityConfigs,
        private string $clientId,
        private string $clientSecret,
    ) {
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
        $userConfig = $this->getUserConfig(localUser: $localUser);

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
    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser,
        string $plainPassword
    ): OidcTokenRequestDto {
        $userConfig = $this->getUserConfig(localUser: $localUser);

        return new OidcTokenRequestDto(
            realm: $userConfig->getRealm(),
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            username: $localUser->getUsername(),
            password: $plainPassword,
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserDeletion(
        KeycloakUserInterface $localUser
    ): DeleteUserDto {
        $userConfig = $this->getUserConfig(localUser: $localUser);

        return new DeleteUserDto(
            realm: $userConfig->getRealm(),
            userId: $localUser->getId(),
        );
    }

    #[Override]
    public function support(KeycloakUserInterface $localUser): bool
    {
        return isset($this->userEntityConfigs[$localUser::class]);
    }

    private function getUserConfig(KeycloakUserInterface $localUser): UserEntityConfig
    {
        $userConfig = $this->userEntityConfigs[$localUser::class] ?? null;
        if ($userConfig === null) {
            throw new LogicException('No user entity configuration for ' . $localUser::class);
        }

        return $userConfig;
    }
}
