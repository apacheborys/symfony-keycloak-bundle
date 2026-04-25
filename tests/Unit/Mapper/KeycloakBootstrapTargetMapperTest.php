<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Mapper;

use Apacheborys\SymfonyKeycloakBridgeBundle\Entity\KeycloakBootstrapTarget;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\KeycloakBootstrapTargetMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use LogicException;
use PHPUnit\Framework\TestCase;

final class KeycloakBootstrapTargetMapperTest extends TestCase
{
    public function testSupportReturnsTrueOnlyForBootstrapTargets(): void
    {
        $mapper = new KeycloakBootstrapTargetMapper();
        $target = new KeycloakBootstrapTarget($this->buildUserEntityConfig());

        self::assertTrue($mapper->support($target));
        self::assertFalse($mapper->support(new LocalUser()));
    }

    public function testGetRealmReturnsRealmFromUserEntityConfig(): void
    {
        $mapper = new KeycloakBootstrapTargetMapper();
        $target = new KeycloakBootstrapTarget($this->buildUserEntityConfig());

        self::assertSame('users-realm', $mapper->getRealm($target));
    }

    public function testCreateOperationMethodsAreRejected(): void
    {
        $mapper = new KeycloakBootstrapTargetMapper();
        $target = new KeycloakBootstrapTarget($this->buildUserEntityConfig());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not intended for user creation');
        $mapper->prepareLocalUserForKeycloakUserCreation($target, []);
    }

    private function buildUserEntityConfig(): UserEntityConfig
    {
        return new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'id',
        );
    }
}
