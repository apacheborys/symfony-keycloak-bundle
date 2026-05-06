<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\CallsignedLocalUserMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\Internal\CallsignValuePrefixer;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\CustomMappedUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Mapper\CustomMappedUserMapper;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CallsignedLocalUserMapperTest extends TestCase
{
    public function testGetLocalUserIdAttributePrefixesLookupValue(): void
    {
        $user = new CustomMappedUser();
        $mapper = new CallsignedLocalUserMapper(
            inner: new CustomMappedUserMapper(),
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );

        $attribute = $mapper->getLocalUserIdAttribute($user);

        self::assertSame('external-user-id', $attribute->getAttributeName());
        self::assertSame(['bridge.' . $user->getId()], $attribute->getNormalizedValues());
    }

    public function testPrepareLocalUserForKeycloakUserCreationPrefixesMappedAttributeValues(): void
    {
        $user = new CustomMappedUser();
        $mapper = new CallsignedLocalUserMapper(
            inner: new CustomMappedUserMapper(),
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );

        $dto = $mapper->prepareLocalUserForKeycloakUserCreation(localUser: $user);

        self::assertSame(
            ['external-user-id' => ['bridge.' . $user->getId()]],
            $dto->getAttributes(),
        );
    }

    public function testPrepareLocalUserRolesForKeycloakUserCreationStripsAndReappliesCallsign(): void
    {
        $existingRoleId = Uuid::fromString('7d466c0e-0894-4f09-9d83-a4afebd84d4b');
        $mapper = new CallsignedLocalUserMapper(
            inner: new CustomMappedUserMapper(),
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );

        $dto = $mapper->prepareLocalUserRolesForKeycloakUserCreation(
            localUser: new CustomMappedUser(),
            availableRoles: [
                new RoleDto(
                    name: 'bridge.ROLE_CUSTOM',
                    id: $existingRoleId,
                ),
            ],
        );

        self::assertNotNull($dto->getRoles());
        self::assertCount(1, $dto->getRoles());
        self::assertSame('bridge.ROLE_CUSTOM', $dto->getRoles()[0]->getName());
        self::assertTrue($existingRoleId->equals($dto->getRoles()[0]->getId()));
    }
}
