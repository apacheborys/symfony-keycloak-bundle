<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Entity\KeycloakBootstrapTarget;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\KeycloakBootstrapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Service\Stub\CapturingUserIdentifierAttributeService;
use LogicException;
use PHPUnit\Framework\TestCase;

final class KeycloakBootstrapperTest extends TestCase
{
    public function testEnsureUserIdentifierAttributeDelegatesToServiceLayerWithBootstrapTarget(): void
    {
        $serviceSpy = new CapturingUserIdentifierAttributeService();

        $bootstrapper = new KeycloakBootstrapper(
            userIdentifierAttributeService: $serviceSpy,
            userEntityConfigs: [$this->buildUserEntityConfig()],
        );

        $bootstrapper->ensureUserIdentifierAttribute(LocalUser::class);

        self::assertInstanceOf(KeycloakBootstrapTarget::class, $serviceSpy->capturedUser);
        self::assertSame(LocalUser::class, $serviceSpy->capturedUser->getUserEntityConfig()->getClassName());

        self::assertInstanceOf(EnsureUserIdentifierAttributeDto::class, $serviceSpy->capturedDto);
        self::assertTrue($serviceSpy->capturedDto->shouldCreateIfMissing());
        self::assertTrue($serviceSpy->capturedDto->shouldExposeInJwt());
        self::assertSame('local-user-id', $serviceSpy->capturedDto->getAttributeName());
        self::assertSame('local_user_id', $serviceSpy->capturedDto->getJwtClaimName());
    }

    public function testEnsureUserIdentifierAttributeRejectsUnknownUserEntityClass(): void
    {
        $bootstrapper = new KeycloakBootstrapper(
            userIdentifierAttributeService: new class implements KeycloakUserIdentifierAttributeServiceInterface {
                public function ensureUserIdentifierAttribute(
                    KeycloakUserInterface $localUser,
                    EnsureUserIdentifierAttributeDto $dto
                ): void {
                }
            },
            userEntityConfigs: [],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not have configuration for user entity');
        $bootstrapper->ensureUserIdentifierAttribute(LocalUser::class);
    }

    private function buildUserEntityConfig(): UserEntityConfig
    {
        return new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'id',
            attributesMap: [
                [
                    'property' => 'id',
                    'attribute_name' => 'local-user-id',
                    'jwt_claim_name' => null,
                    'create_if_missing' => false,
                ],
            ],
        );
    }
}
