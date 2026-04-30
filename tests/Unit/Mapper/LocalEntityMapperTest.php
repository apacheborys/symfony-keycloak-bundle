<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use LogicException;
use PHPUnit\Framework\TestCase;

final class LocalEntityMapperTest extends TestCase
{
    public function testPrepareLocalUserForKeycloakUserCreationReturnsPlaceholderRoleWhenAutoCreationIsEnabled(): void
    {
        $mapper = new LocalEntityMapper(
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                    userIdentifierField: 'id',
                    roleAllowCreation: true,
                    rolePrefix: 'payment.',
                    roleSuffix: '.svc',
                ),
            ],
            clientId: 'bridge-client',
            clientSecret: 'bridge-secret',
        );

        $dto = $mapper->prepareLocalUserForKeycloakUserCreation(
            localUser: new LocalUser(roles: ['ROLE_USER']),
            availableRoles: [],
        );

        self::assertCount(1, $dto->getRoles());
        self::assertSame('payment.ROLE_USER.svc', $dto->getRoles()[0]->getName());
        self::assertNull($dto->getRoles()[0]->getId());
    }

    public function testPrepareLocalUserForKeycloakUserCreationRejectsMissingRoleWhenAutoCreationIsDisabled(): void
    {
        $mapper = new LocalEntityMapper(
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                    userIdentifierField: 'id',
                    roleAllowCreation: false,
                    rolePrefix: 'payment.',
                    roleSuffix: '.svc',
                ),
            ],
            clientId: 'bridge-client',
            clientSecret: 'bridge-secret',
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('role.allow_creation is disabled');

        $mapper->prepareLocalUserForKeycloakUserCreation(
            localUser: new LocalUser(roles: ['ROLE_USER']),
            availableRoles: [],
        );
    }

    public function testPrepareLocalUserForKeycloakUserCreationUsesExistingRoleWhenAvailable(): void
    {
        $existingRole = new RoleDto(name: 'payment.ROLE_USER.svc');
        $mapper = new LocalEntityMapper(
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                    userIdentifierField: 'id',
                    roleAllowCreation: false,
                    rolePrefix: 'payment.',
                    roleSuffix: '.svc',
                ),
            ],
            clientId: 'bridge-client',
            clientSecret: 'bridge-secret',
        );

        $dto = $mapper->prepareLocalUserForKeycloakUserCreation(
            localUser: new LocalUser(roles: ['ROLE_USER']),
            availableRoles: [$existingRole],
        );

        self::assertSame($existingRole, $dto->getRoles()[0]);
    }
}
