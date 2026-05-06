<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\UserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\AttributeValueDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\Internal\CallsignValuePrefixer;
use LogicException;
use Override;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

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
        private CallsignValuePrefixer $callsignValuePrefixer,
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

    #[Override]
    public function getLocalUserIdAttribute(KeycloakUserInterface $localUser): AttributeValueDto
    {
        $userConfig = $this->getUserConfig(localUser: $localUser);
        $identifierAttributeConfig = $userConfig->getUserIdentifierAttributeConfig();

        return new AttributeValueDto(
            attributeName: $identifierAttributeConfig->getAttributeName(),
            attributeValue: $this->callsignValuePrefixer->prefix(
                $identifierAttributeConfig->resolveValue($localUser)
            ),
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser
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
            attributes: $this->buildMappedAttributes(
                localUser: $localUser,
                userConfig: $userConfig,
            ),
        );
    }

    /**
     * @param list<RoleDto> $availableRoles
     */
    #[Override]
    public function prepareLocalUserRolesForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): UserRolesDto {
        $userConfig = $this->getUserConfig(localUser: $localUser);

        return new UserRolesDto(
            realm: $userConfig->getRealm(),
            roles: $this->resolveRoles(
                localRoleNames: $localUser->getRoles(),
                availableRoles: $availableRoles,
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
            userId: $this->resolveOptionalKeycloakUserId(localUser: $localUser),
            localUserId: $localUser->getId(),
        );
    }

    #[Override]
    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): UpdateUserDto {
        $newUserConfig = $this->getUserConfig(localUser: $newUserVersion);
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
            attributes: $this->buildMappedAttributes(
                localUser: $newUserVersion,
                userConfig: $newUserConfig,
            ),
        );

        return new UpdateUserDto(
            realm: $newUserConfig->getRealm(),
            profile: $profile,
            userId: $this->resolveOptionalKeycloakUserIdForUpdate(
                oldUserVersion: $oldUserVersion,
                newUserVersion: $newUserVersion,
            ),
            localUserId: $newUserVersion->getId(),
        );
    }

    /**
     * @param list<RoleDto> $availableRoles
     */
    #[Override]
    public function prepareLocalUserRolesForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UserRolesDto {
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

        return new UserRolesDto(
            realm: $newUserConfig->getRealm(),
            roles: $oldRoles === $newRoles
                ? null
                : $this->resolveRoles(
                    localRoleNames: $newUserVersion->getRoles(),
                    availableRoles: $availableRoles,
                    userConfig: $newUserConfig,
                ),
        );
    }

    #[Override]
    public function support(KeycloakUserInterface $localUser): bool
    {
        $userConfig = $this->findUserConfig(localUser: $localUser);

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
            $availableRole = $availableByName[$roleName] ?? null;
            if ($availableRole instanceof RoleDto) {
                $resolved[] = $availableRole;
                continue;
            }

            if (!$userConfig->isRoleCreationAllowed()) {
                throw new LogicException(
                    sprintf(
                        'Role "%s" is missing in Keycloak and role.allow_creation is disabled for "%s".',
                        $roleName,
                        $userConfig->getClassName(),
                    )
                );
            }

            $resolved[] = new RoleDto(name: $roleName);
        }

        return $resolved;
    }

    /**
     * @return array<string, string>
     */
    private function buildMappedAttributes(
        KeycloakUserInterface $localUser,
        UserEntityConfig $userConfig
    ): array {
        return $this->callsignValuePrefixer->prefixAttributeMap(
            $userConfig->resolveMappedAttributes(localUser: $localUser),
        );
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

            $projected[] = $this->callsignValuePrefixer->prefix(
                $userConfig->getRolePrefix() . $trimmedRoleName . $userConfig->getRoleSuffix()
            );
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

    private function resolveOptionalKeycloakUserId(KeycloakUserInterface $localUser): ?UuidInterface
    {
        $keycloakId = $localUser->getKeycloakId();
        if ($keycloakId === null) {
            return null;
        }

        return Uuid::fromString($keycloakId);
    }

    private function resolveOptionalKeycloakUserIdForUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
    ): ?UuidInterface {
        return $this->resolveOptionalKeycloakUserId(localUser: $newUserVersion)
            ?? $this->resolveOptionalKeycloakUserId(localUser: $oldUserVersion);
    }

    private function getUserConfig(KeycloakUserInterface $localUser): UserEntityConfig
    {
        $userConfig = $this->findUserConfig(localUser: $localUser);
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

    private function findUserConfig(KeycloakUserInterface $localUser): ?UserEntityConfig
    {
        $directMatch = $this->userEntityConfigs[$localUser::class] ?? null;
        if ($directMatch instanceof UserEntityConfig) {
            return $directMatch;
        }

        foreach ($this->userEntityConfigs as $configuredClass => $userEntityConfig) {
            if ($localUser instanceof $configuredClass) {
                return $userEntityConfig;
            }
        }

        return null;
    }
}
