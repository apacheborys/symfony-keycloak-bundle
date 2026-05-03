<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Model;

use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\MethodBasedIdentifierUser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserEntityConfigTest extends TestCase
{
    public function testResolvesPrivateConfiguredUserIdentifierField(): void
    {
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            roleAllowCreation: true,
            rolePrefix: 'payment.',
            roleSuffix: '.svc',
            attributesMap: [
                [
                    'property' => 'id',
                    'attribute_name' => 'local-user-id',
                    'jwt_claim_name' => null,
                    'create_if_missing' => true,
                    'required' => [
                        'roles' => ['admin'],
                        'scopes' => ['openid'],
                    ],
                ],
                [
                    'property' => 'firstName',
                    'attribute_name' => 'profile-first-name',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                    'required' => false,
                ],
            ],
        );

        self::assertTrue($config->isRoleCreationAllowed());
        self::assertSame('payment.', $config->getRolePrefix());
        self::assertSame('.svc', $config->getRoleSuffix());
        self::assertCount(2, $config->getAttributeConfigs());
        self::assertSame(['local-user-id', 'local_user_id'], $config->getUserIdentifierJwtClaimNames());
        self::assertSame(
            ['roles' => ['admin'], 'scopes' => ['openid']],
            $config->getUserIdentifierAttributeConfig()->getRequired()?->toArray(),
        );
        self::assertSame('local-user-id', $config->buildEnsureUserIdentifierAttributeDto()->getAttributeName());
        self::assertSame(
            [
                'local-user-id' => '58f5b67f-bcf4-4d12-86a3-a54f7704f326',
                'profile-first-name' => 'Local',
            ],
            $config->resolveMappedAttributes(new LocalUser()),
        );
    }

    public function testRejectsUnknownConfiguredAttributeProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configured attribute property "unknownIdentifierField" was not found on');

        new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            attributesMap: [
                [
                    'property' => 'unknownIdentifierField',
                    'attribute_name' => 'unknown-identifier',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                ],
            ],
        );
    }

    public function testUsesClientDefaultLocalUserIdAttributeNameForImplicitIdentifierMapping(): void
    {
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
        );

        self::assertCount(1, $config->getAttributeConfigs());
        self::assertSame('external-user-id', $config->getUserIdentifierAttributeConfig()->getAttributeName());
        self::assertSame('external_user_id', $config->getUserIdentifierAttributeConfig()->getJwtClaimName());
        self::assertSame(['external-user-id', 'external_user_id'], $config->getUserIdentifierJwtClaimNames());
        self::assertFalse($config->getUserIdentifierAttributeConfig()->hasRequiredConfiguration());
    }

    public function testBuildBootstrapUserIdentifierAttributeDtoForcesCreateIfMissing(): void
    {
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
        );

        $dto = $config->buildBootstrapUserIdentifierAttributeDto();

        self::assertTrue($dto->shouldCreateIfMissing());
        self::assertTrue($dto->shouldExposeInJwt());
        self::assertSame('external-user-id', $dto->getAttributeName());
        self::assertSame('external_user_id', $dto->getJwtClaimName());
    }

    public function testTracksExplicitRequiredDisableSeparatelyFromOmittedRequired(): void
    {
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            attributesMap: [
                [
                    'property' => 'id',
                    'attribute_name' => null,
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                    'required' => false,
                ],
            ],
        );

        self::assertTrue($config->getUserIdentifierAttributeConfig()->hasRequiredConfiguration());
        self::assertSame('external-user-id', $config->getUserIdentifierAttributeConfig()->getAttributeName());
        self::assertSame('external_user_id', $config->getUserIdentifierAttributeConfig()->getJwtClaimName());
        self::assertNull($config->getUserIdentifierAttributeConfig()->getRequired());
    }

    public function testRejectsDuplicatedAttributeProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configured attribute property "id" is duplicated');

        new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            attributesMap: [
                [
                    'property' => 'id',
                    'attribute_name' => 'local-user-id',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                ],
                [
                    'property' => 'id',
                    'attribute_name' => 'local-user-id-2',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                ],
            ],
        );
    }

    public function testResolvesIdentifierFromGetIdWithoutBackingIdProperty(): void
    {
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: MethodBasedIdentifierUser::class,
            attributesMap: [
                [
                    'property' => 'id',
                    'attribute_name' => 'local-user-id',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                ],
            ],
        );

        self::assertSame(
            ['local-user-id' => 'method-based-local-user-id'],
            $config->resolveMappedAttributes(new MethodBasedIdentifierUser()),
        );
    }
}
