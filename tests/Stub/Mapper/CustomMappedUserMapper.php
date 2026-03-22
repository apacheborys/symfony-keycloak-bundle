<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
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
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): CreateUserProfileDto {
        return new CreateUserProfileDto(
            username: $localUser->getUsername(),
            email: $localUser->getEmail(),
            emailVerified: $localUser->isEmailVerified(),
            enabled: $localUser->isEnabled(),
            firstName: $localUser->getFirstName(),
            lastName: $localUser->getLastName(),
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
        return new DeleteUserDto(
            realm: $this->getRealm($localUser),
            userId: Uuid::fromString($localUser->getId()),
        );
    }

    #[Override]
    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UpdateUserDto {
        return new UpdateUserDto(
            realm: $this->getRealm($newUserVersion),
            userId: Uuid::fromString($newUserVersion->getId()),
            profile: new UpdateUserProfileDto(
                username: $newUserVersion->getUsername(),
                email: $newUserVersion->getEmail(),
                emailVerified: $newUserVersion->isEmailVerified(),
                enabled: $newUserVersion->isEnabled(),
                firstName: $newUserVersion->getFirstName(),
                lastName: $newUserVersion->getLastName(),
                roles: $this->resolveRoles(
                    localRoles: $newUserVersion->getRoles(),
                    availableRoles: $availableRoles,
                ),
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
