<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Model;

use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserEntityConfigTest extends TestCase
{
    public function testResolvesPrivateConfiguredUserIdentifierField(): void
    {
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'localIdentifier',
        );

        self::assertSame('localIdentifier', $config->getUserIdentifierField());
        self::assertSame(
            'local-user-reference-58f5b67f-bcf4-4d12-86a3-a54f7704f326',
            $config->resolveUserIdentifierValue(new LocalUser())
        );
    }

    public function testRejectsUnknownConfiguredUserIdentifierField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Configured user identifier field "unknownIdentifierField" was not found on'
        );

        new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'unknownIdentifierField',
        );
    }
}
