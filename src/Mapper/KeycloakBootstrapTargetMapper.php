<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Entity\KeycloakBootstrapTarget;
use LogicException;
use Override;

final class KeycloakBootstrapTargetMapper implements LocalKeycloakUserBridgeMapperInterface
{
    #[Override]
    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return $this->getBootstrapTarget(localUser: $localUser)->getUserEntityConfig()->getRealm();
    }

    /**
     * @param list<RoleDto> $availableRoles
     */
    #[Override]
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): CreateUserProfileDto {
        throw new LogicException('Keycloak bootstrap targets are not intended for user creation.');
    }

    #[Override]
    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser,
        string $plainPassword
    ): OidcTokenRequestDto {
        throw new LogicException('Keycloak bootstrap targets are not intended for login.');
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserDeletion(
        KeycloakUserInterface $localUser
    ): DeleteUserDto {
        throw new LogicException('Keycloak bootstrap targets are not intended for deletion.');
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
        throw new LogicException('Keycloak bootstrap targets are not intended for update diff mapping.');
    }

    #[Override]
    public function support(KeycloakUserInterface $localUser): bool
    {
        return $localUser instanceof KeycloakBootstrapTarget;
    }

    private function getBootstrapTarget(KeycloakUserInterface $localUser): KeycloakBootstrapTarget
    {
        if ($localUser instanceof KeycloakBootstrapTarget) {
            return $localUser;
        }

        throw new LogicException(
            sprintf('Expected "%s", got "%s".', KeycloakBootstrapTarget::class, $localUser::class)
        );
    }
}
