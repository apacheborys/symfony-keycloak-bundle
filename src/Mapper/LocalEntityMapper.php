<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use LogicException;
use Override;
use Ramsey\Uuid\Uuid;

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
            $configs[$config->getClassName()] = $config;
        }

        $this->userEntityConfigs = $configs;
    }

    #[Override]
    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return $this->getUserConfig(localUser: $localUser)->getRealm();
    }

    /**
     * @param list<RoleDto> $availableRoles
     */
    #[Override]
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): CreateUserProfileDto {
        $userConfig = $this->getUserConfig(localUser: $localUser);

        return new CreateUserProfileDto(
            username: $localUser->getUsername(),
            email: $localUser->getEmail(),
            emailVerified: $localUser->isEmailVerified(),
            enabled: $localUser->isEnabled(),
            firstName: $localUser->getFirstName(),
            lastName: $localUser->getLastName(),
            realm: $userConfig->getRealm(),
            roles: $this->resolveRoles(
                localRoleNames: $localUser->getRoles(),
                availableRoles: $availableRoles,
                userConfig: $userConfig,
            ),
            attributes: $this->buildIdentifierAttributes(
                localUser: $localUser,
                userConfig: $userConfig,
            ),
        );
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
            userId: Uuid::fromString($localUser->getId()),
        );
    }

    /**
     * @param list<RoleDto> $availableRoles
     */
    #[Override]
    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UpdateUserDto {
        $oldUserConfig = $this->getUserConfig(localUser: $oldUserVersion);
        $newUserConfig = $this->getUserConfig(localUser: $newUserVersion);

        $oldRoles = $this->normalizeRoleNames(
            roleNames: $this->projectRoleNames(
                localRoleNames: $oldUserVersion->getRoles(),
                userConfig: $oldUserConfig,
            ),
        );
        $newRoles = $this->normalizeRoleNames(
            roleNames: $this->projectRoleNames(
                localRoleNames: $newUserVersion->getRoles(),
                userConfig: $newUserConfig,
            ),
        );
        $roles = $oldRoles === $newRoles
            ? null
            : $this->resolveRoles(
                localRoleNames: $newUserVersion->getRoles(),
                availableRoles: $availableRoles,
                userConfig: $newUserConfig,
            );

        $email = $oldUserVersion->getEmail() === $newUserVersion->getEmail()
            ? null
            : $newUserVersion->getEmail();

        $enabled = $oldUserVersion->isEnabled() === $newUserVersion->isEnabled()
            ? null
            : $newUserVersion->isEnabled();

        $lastName = $oldUserVersion->getLastName() === $newUserVersion->getLastName()
            ? null
            : $newUserVersion->getLastName();

        $profile = new UpdateUserProfileDto(
            username: $newUserVersion->getUsername(),
            email: $email,
            emailVerified: $oldUserVersion->isEmailVerified() === $newUserVersion->isEmailVerified()
                ? null
                : $newUserVersion->isEmailVerified(),
            enabled: $enabled,
            firstName: $oldUserVersion->getFirstName() === $newUserVersion->getFirstName()
                ? null
                : $newUserVersion->getFirstName(),
            lastName: $lastName,
            roles: $roles,
            attributes: $this->buildIdentifierAttributes(
                localUser: $newUserVersion,
                userConfig: $newUserConfig,
            ),
        );

        return new UpdateUserDto(
            realm: $newUserConfig->getRealm(),
            userId: Uuid::fromString($newUserVersion->getId()),
            profile: $profile,
        );
    }

    #[Override]
    public function support(KeycloakUserInterface $localUser): bool
    {
        $userConfig = $this->userEntityConfigs[$localUser::class] ?? null;

        return $userConfig instanceof UserEntityConfig
            && $userConfig->getMapper() === self::class;
    }

    /**
     * @param string[] $localRoleNames
     * @param list<RoleDto> $availableRoles
     * @param UserEntityConfig $userConfig
     * @return list<RoleDto>
     */
    private function resolveRoles(array $localRoleNames, array $availableRoles, UserEntityConfig $userConfig): array
    {
        $availableByName = [];
        foreach ($availableRoles as $role) {
            $availableByName[$role->getName()] = $role;
        }

        $resolved = [];
        foreach (
            $this->normalizeRoleNames(
                roleNames: $this->projectRoleNames(
                    localRoleNames: $localRoleNames,
                    userConfig: $userConfig,
                ),
            ) as $roleName
        ) {
            $resolved[] = $availableByName[$roleName] ?? new RoleDto(name: $roleName);
        }

        return $resolved;
    }

    /**
     * @return array<string, string>
     */
    private function buildIdentifierAttributes(
        KeycloakUserInterface $localUser,
        UserEntityConfig $userConfig
    ): array {
        return [
            $userConfig->getUserIdentifierField() => $userConfig->resolveUserIdentifierValue($localUser),
        ];
    }

    /**
     * @param string[] $localRoleNames
     * @return list<string>
     */
    private function projectRoleNames(array $localRoleNames, UserEntityConfig $userConfig): array
    {
        $projected = [];
        foreach ($localRoleNames as $localRoleName) {
            $trimmedRoleName = trim($localRoleName);
            if ($trimmedRoleName === '') {
                continue;
            }

            $projected[] = $userConfig->getRolePrefix() . $trimmedRoleName . $userConfig->getRoleSuffix();
        }

        return $projected;
    }

    /**
     * @param string[] $roleNames
     * @return list<string>
     */
    private function normalizeRoleNames(array $roleNames): array
    {
        $normalized = [];
        foreach ($roleNames as $roleName) {
            $trimmedRoleName = trim($roleName);
            if ($trimmedRoleName === '') {
                continue;
            }

            $normalized[$trimmedRoleName] = true;
        }

        return array_keys($normalized);
    }

    private function getUserConfig(KeycloakUserInterface $localUser): UserEntityConfig
    {
        $userConfig = $this->userEntityConfigs[$localUser::class] ?? null;
        if ($userConfig === null) {
            throw new LogicException('No user entity configuration for ' . $localUser::class);
        }

        if ($userConfig->getMapper() !== self::class) {
            throw new LogicException(
                sprintf(
                    'User entity "%s" is configured to use mapper "%s", not "%s".',
                    $localUser::class,
                    $userConfig->getMapper(),
                    self::class
                )
            );
        }

        return $userConfig;
    }
}
