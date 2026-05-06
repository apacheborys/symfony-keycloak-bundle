<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\Internal\CallsignValuePrefixer;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use LogicException;
use PHPUnit\Framework\TestCase;

final class LocalEntityMapperTest extends TestCase
{
    public function testPrepareLocalUserRolesForKeycloakUserCreationReturnsPlaceholderRoleWhenAutoCreationIsEnabled(): void
    {
        $mapper = new LocalEntityMapper(
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                    roleAllowCreation: true,
                    rolePrefix: 'payment.',
                    roleSuffix: '.svc',
                ),
            ],
            clientId: 'bridge-client',
            clientSecret: 'bridge-secret',
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );

        $dto = $mapper->prepareLocalUserRolesForKeycloakUserCreation(
            localUser: new LocalUser(roles: ['ROLE_USER']),
            availableRoles: [],
        );

        self::assertNotNull($dto->getRoles());
        self::assertCount(1, $dto->getRoles());
        self::assertSame('bridge.payment.ROLE_USER.svc', $dto->getRoles()[0]->getName());
        self::assertNull($dto->getRoles()[0]->getId());
    }

    public function testPrepareLocalUserRolesForKeycloakUserCreationRejectsMissingRoleWhenAutoCreationIsDisabled(): void
    {
        $mapper = new LocalEntityMapper(
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                    roleAllowCreation: false,
                    rolePrefix: 'payment.',
                    roleSuffix: '.svc',
                ),
            ],
            clientId: 'bridge-client',
            clientSecret: 'bridge-secret',
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('role.allow_creation is disabled');

        $mapper->prepareLocalUserRolesForKeycloakUserCreation(
            localUser: new LocalUser(roles: ['ROLE_USER']),
            availableRoles: [],
        );
    }

    public function testPrepareLocalUserRolesForKeycloakUserCreationUsesExistingRoleWhenAvailable(): void
    {
        $existingRole = new RoleDto(name: 'bridge.payment.ROLE_USER.svc');
        $mapper = new LocalEntityMapper(
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                    roleAllowCreation: false,
                    rolePrefix: 'payment.',
                    roleSuffix: '.svc',
                ),
            ],
            clientId: 'bridge-client',
            clientSecret: 'bridge-secret',
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );

        $dto = $mapper->prepareLocalUserRolesForKeycloakUserCreation(
            localUser: new LocalUser(roles: ['ROLE_USER']),
            availableRoles: [$existingRole],
        );

        self::assertNotNull($dto->getRoles());
        self::assertSame($existingRole, $dto->getRoles()[0]);
    }

    public function testPrepareLocalUserForKeycloakUserDeletionKeepsLocalUserIdWhenKeycloakIdIsMissing(): void
    {
        $mapper = new LocalEntityMapper(
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                ),
            ],
            clientId: 'bridge-client',
            clientSecret: 'bridge-secret',
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );

        $user = new LocalUser(keycloakId: null);
        $dto = $mapper->prepareLocalUserForKeycloakUserDeletion($user);

        self::assertNull($dto->getUserId());
        self::assertSame($user->getId(), $dto->getLocalUserId());
    }

    public function testImplicitIdentifierMappingUsesClientDefaultAttributeName(): void
    {
        $mapper = new LocalEntityMapper(
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                ),
            ],
            clientId: 'bridge-client',
            clientSecret: 'bridge-secret',
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );

        $user = new LocalUser();
        $dto = $mapper->prepareLocalUserForKeycloakUserCreation($user);

        $localUserIdAttribute = $mapper->getLocalUserIdAttribute($user);

        self::assertSame('external-user-id', $localUserIdAttribute->getAttributeName());
        self::assertSame(['bridge.58f5b67f-bcf4-4d12-86a3-a54f7704f326'], $localUserIdAttribute->getNormalizedValues());
        self::assertSame(
            ['external-user-id' => ['bridge.58f5b67f-bcf4-4d12-86a3-a54f7704f326']],
            $dto->getAttributes(),
        );
    }
}
