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
            attributesMap: [
                [
                    'property' => 'localIdentifier',
                    'attribute_name' => 'local-user-id',
                    'jwt_claim_name' => 'local_user_id',
                    'create_if_missing' => true,
                ],
                [
                    'property' => 'firstName',
                    'attribute_name' => 'profile-first-name',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                ],
            ],
        );

        self::assertSame('localIdentifier', $config->getUserIdentifierField());
        self::assertCount(2, $config->getAttributeConfigs());
        self::assertSame(['local-user-id', 'local_user_id'], $config->getUserIdentifierJwtClaimNames());
        self::assertSame('local-user-id', $config->buildEnsureUserIdentifierAttributeDto()->getAttributeName());
        self::assertSame(
            [
                'local-user-id' => 'local-user-reference-58f5b67f-bcf4-4d12-86a3-a54f7704f326',
                'profile-first-name' => 'Local',
            ],
            $config->resolveMappedAttributes(new LocalUser()),
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

    public function testFallsBackToUserIdentifierFieldAsAttributeName(): void
    {
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'localIdentifier',
        );

        self::assertCount(1, $config->getAttributeConfigs());
        self::assertSame('localIdentifier', $config->getUserIdentifierAttributeConfig()->getAttributeName());
        self::assertNull($config->getUserIdentifierAttributeConfig()->getJwtClaimName());
        self::assertSame(['localIdentifier'], $config->getUserIdentifierJwtClaimNames());
    }

    public function testRejectsDuplicatedAttributeProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configured attribute property "localIdentifier" is duplicated');

        new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'localIdentifier',
            attributesMap: [
                [
                    'property' => 'localIdentifier',
                    'attribute_name' => 'local-user-id',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                ],
                [
                    'property' => 'localIdentifier',
                    'attribute_name' => 'local-user-id-2',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                ],
            ],
        );
    }
}
