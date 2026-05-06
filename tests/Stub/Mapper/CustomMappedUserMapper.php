<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Mapper;

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
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\CustomMappedUser;
use Override;
use Ramsey\Uuid\Uuid;

final class CustomMappedUserMapper implements LocalKeycloakUserBridgeMapperInterface
{
    #[Override]
    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return 'custom-realm';
    }

    #[Override]
    public function getLocalUserIdAttribute(KeycloakUserInterface $localUser): AttributeValueDto
    {
        return new AttributeValueDto(
            attributeName: self::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME,
            attributeValue: (string) $localUser->getId(),
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser
    ): CreateUserProfileDto {
        return new CreateUserProfileDto(
            username: $localUser->getUsername(),
            email: $localUser->getEmail(),
            emailVerified: $localUser->isEmailVerified(),
            enabled: $localUser->isEnabled(),
            firstName: $localUser->getFirstName(),
            lastName: $localUser->getLastName(),
            realm: $this->getRealm($localUser),
            attributes: [$this->getLocalUserIdAttribute($localUser)],
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
        return new UserRolesDto(
            realm: $this->getRealm($localUser),
            roles: $this->resolveRoles(
                localRoles: $localUser->getRoles(),
                availableRoles: $availableRoles,
            ),
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser,
        string $plainPassword
    ): OidcTokenRequestDto {
        return new OidcTokenRequestDto(
            realm: $this->getRealm($localUser),
            clientId: 'custom-client',
            clientSecret: 'custom-secret',
            username: $localUser->getUsername(),
            password: $plainPassword,
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserDeletion(KeycloakUserInterface $localUser): DeleteUserDto
    {
        $keycloakId = $localUser->getKeycloakId();

        return new DeleteUserDto(
            realm: $this->getRealm($localUser),
            userId: $keycloakId !== null ? Uuid::fromString($keycloakId) : null,
            localUserId: $localUser->getId(),
        );
    }

    #[Override]
    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): UpdateUserDto {
        $keycloakId = $newUserVersion->getKeycloakId() ?? $oldUserVersion->getKeycloakId();

        return new UpdateUserDto(
            realm: $this->getRealm($newUserVersion),
            profile: new UpdateUserProfileDto(
                username: $newUserVersion->getUsername(),
                email: $newUserVersion->getEmail(),
                emailVerified: $newUserVersion->isEmailVerified(),
                enabled: $newUserVersion->isEnabled(),
                firstName: $newUserVersion->getFirstName(),
                lastName: $newUserVersion->getLastName(),
                attributes: [$this->getLocalUserIdAttribute($newUserVersion)],
            ),
            userId: $keycloakId !== null ? Uuid::fromString($keycloakId) : null,
            localUserId: $newUserVersion->getId(),
        );
    }

    #[Override]
    public function prepareLocalUserRolesForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UserRolesDto {
        return new UserRolesDto(
            realm: $this->getRealm($newUserVersion),
            roles: $this->resolveRoles(
                localRoles: $newUserVersion->getRoles(),
                availableRoles: $availableRoles,
            ),
        );
    }

    #[Override]
    public function support(KeycloakUserInterface $localUser): bool
    {
        return $localUser instanceof CustomMappedUser;
    }

    /**
     * @param string[] $localRoles
     * @param list<RoleDto> $availableRoles
     * @return list<RoleDto>
     */
    private function resolveRoles(array $localRoles, array $availableRoles): array
    {
        $availableByName = [];
        foreach ($availableRoles as $availableRole) {
            $availableByName[$availableRole->getName()] = $availableRole;
        }

        $resolved = [];
        foreach ($localRoles as $localRole) {
            $trimmedRole = trim($localRole);
            if ($trimmedRole === '') {
                continue;
            }

            $resolved[] = $availableByName[$trimmedRole] ?? new RoleDto(name: $trimmedRole);
        }

        return $resolved;
    }
}
