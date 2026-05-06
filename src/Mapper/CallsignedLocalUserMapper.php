<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\UserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\AttributeValueDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\Internal\CallsignValuePrefixer;
use Override;

final readonly class CallsignedLocalUserMapper implements LocalKeycloakUserBridgeMapperInterface
{
    public function __construct(
        private LocalKeycloakUserBridgeMapperInterface $inner,
        private CallsignValuePrefixer $callsignValuePrefixer,
    ) {
    }

    #[Override]
    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return $this->inner->getRealm(localUser: $localUser);
    }

    #[Override]
    public function getLocalUserIdAttribute(KeycloakUserInterface $localUser): AttributeValueDto
    {
        return $this->callsignValuePrefixer->prefixAttribute(
            $this->inner->getLocalUserIdAttribute(localUser: $localUser),
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser
    ): CreateUserProfileDto {
        $profile = $this->inner->prepareLocalUserForKeycloakUserCreation(localUser: $localUser);
        $profilePayload = $profile->toArray();

        return new CreateUserProfileDto(
            username: $profilePayload['username'],
            email: $profilePayload['email'],
            emailVerified: $profilePayload['emailVerified'],
            enabled: $profilePayload['enabled'],
            firstName: $profilePayload['firstName'],
            lastName: $profilePayload['lastName'],
            realm: $profile->getRealm(),
            attributes: $this->callsignValuePrefixer->prefixAttributes($profile->getAttributeDtos()),
        );
    }

    #[Override]
    public function prepareLocalUserRolesForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): UserRolesDto {
        $rolesDto = $this->inner->prepareLocalUserRolesForKeycloakUserCreation(
            localUser: $localUser,
            availableRoles: $this->callsignValuePrefixer->stripRoles($availableRoles),
        );

        return new UserRolesDto(
            realm: $rolesDto->getRealm(),
            roles: $this->callsignValuePrefixer->prefixRoles($rolesDto->getRoles()),
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser,
        string $plainPassword
    ): OidcTokenRequestDto {
        return $this->inner->prepareLocalUserForKeycloakLoginUser(
            localUser: $localUser,
            plainPassword: $plainPassword,
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserDeletion(
        KeycloakUserInterface $localUser
    ): DeleteUserDto {
        return $this->inner->prepareLocalUserForKeycloakUserDeletion(localUser: $localUser);
    }

    #[Override]
    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): UpdateUserDto {
        $dto = $this->inner->prepareLocalUserDiffForKeycloakUserUpdate(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );
        $profile = $dto->getProfile();
        $profilePayload = $profile->toArray();

        return new UpdateUserDto(
            realm: $dto->getRealm(),
            profile: new UpdateUserProfileDto(
                username: $profile->getUsername(),
                email: $profile->getEmail(),
                emailVerified: $profilePayload['emailVerified'] ?? null,
                enabled: $profilePayload['enabled'] ?? null,
                firstName: $profilePayload['firstName'] ?? null,
                lastName: $profilePayload['lastName'] ?? null,
                attributes: $profile->getAttributeDtos() !== null
                    ? $this->callsignValuePrefixer->prefixAttributes($profile->getAttributeDtos())
                    : null,
            ),
            userId: $dto->getUserId(),
            localUserId: $dto->getLocalUserId(),
        );
    }

    #[Override]
    public function prepareLocalUserRolesForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UserRolesDto {
        $rolesDto = $this->inner->prepareLocalUserRolesForKeycloakUserUpdate(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
            availableRoles: $this->callsignValuePrefixer->stripRoles($availableRoles),
        );

        return new UserRolesDto(
            realm: $rolesDto->getRealm(),
            roles: $this->callsignValuePrefixer->prefixRoles($rolesDto->getRoles()),
        );
    }

    #[Override]
    public function support(KeycloakUserInterface $localUser): bool
    {
        return $this->inner->support(localUser: $localUser);
    }
}
