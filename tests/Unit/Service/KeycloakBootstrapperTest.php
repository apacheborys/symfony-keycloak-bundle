<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeRequiredDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\KeycloakBootstrapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Service\Stub\CapturingUserIdentifierAttributeService;
use LogicException;
use PHPUnit\Framework\TestCase;

final class KeycloakBootstrapperTest extends TestCase
{
    public function testEnsureUserIdentifierAttributeDelegatesToServiceLayerWithResolvedRealm(): void
    {
        $serviceSpy = new CapturingUserIdentifierAttributeService();
        $httpClient = new TestKeycloakHttpClient();

        $bootstrapper = new KeycloakBootstrapper(
            userIdentifierAttributeService: $serviceSpy,
            httpClient: $httpClient,
            userEntityConfigs: [$this->buildUserEntityConfig()],
        );

        $bootstrapper->ensureUserIdentifierAttribute(LocalUser::class);

        self::assertSame('users-realm', $serviceSpy->capturedRealm);
        self::assertInstanceOf(EnsureUserIdentifierAttributeDto::class, $serviceSpy->capturedDto);
        self::assertTrue($serviceSpy->capturedDto->shouldCreateIfMissing());
        self::assertTrue($serviceSpy->capturedDto->shouldExposeInJwt());
        self::assertSame('local-user-id', $serviceSpy->capturedDto->getAttributeName());
        self::assertSame('local_user_id', $serviceSpy->capturedDto->getJwtClaimName());
        self::assertSame([], $httpClient->getCalls());
    }

    public function testEnsureConfiguredAttributesDelegatesForEachAttributeThatNeedsBootstrapSync(): void
    {
        $serviceSpy = new CapturingUserIdentifierAttributeService();
        $bootstrapper = new KeycloakBootstrapper(
            userIdentifierAttributeService: $serviceSpy,
            httpClient: new TestKeycloakHttpClient(),
            userEntityConfigs: [
                new UserEntityConfig(
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
                        [
                            'property' => 'firstName',
                            'attribute_name' => 'profile-first-name',
                            'jwt_claim_name' => 'profile_first_name',
                            'create_if_missing' => true,
                        ],
                    ],
                ),
            ],
        );

        $bootstrapper->ensureConfiguredAttributes(LocalUser::class);

        self::assertCount(2, $serviceSpy->calls);
        self::assertSame(
            ['local-user-id', 'profile-first-name'],
            array_map(
                static fn (array $call): string => $call['dto']->getAttributeName(),
                $serviceSpy->calls,
            ),
        );
    }

    public function testEnsureUserIdentifierAttributeSynchronizesExplicitlyDisabledRequiredRule(): void
    {
        $serviceSpy = new CapturingUserIdentifierAttributeService();
        $httpClient = new TestKeycloakHttpClient();
        $httpClient->queueResult(
            method: 'getUserProfile',
            result: new UserProfileDto(
                attributes: [
                    new AttributeDto(
                        name: 'local-user-id',
                        displayName: 'Local User Id',
                        permissions: ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                        annotations: ['inputType' => 'text'],
                        required: new AttributeRequiredDto(
                            roles: ['admin', 'user'],
                        ),
                    ),
                ],
            ),
        );
        $httpClient->queueResult(
            method: 'updateUserProfileAttribute',
            result: new UserProfileDto(),
        );

        $bootstrapper = new KeycloakBootstrapper(
            userIdentifierAttributeService: $serviceSpy,
            httpClient: $httpClient,
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                    userIdentifierField: 'id',
                    attributesMap: [
                        [
                            'property' => 'id',
                            'attribute_name' => 'local-user-id',
                            'jwt_claim_name' => null,
                            'create_if_missing' => false,
                            'required' => false,
                        ],
                    ],
                ),
            ],
        );

        $bootstrapper->ensureUserIdentifierAttribute(LocalUser::class);

        $calls = $httpClient->getCalls();
        self::assertCount(2, $calls);
        self::assertSame('getUserProfile', $calls[0]['method']);
        self::assertSame('updateUserProfileAttribute', $calls[1]['method']);
        self::assertInstanceOf(GetUserProfileDto::class, $calls[0]['args'][0]);
        self::assertInstanceOf(UpdateUserProfileAttributeDto::class, $calls[1]['args'][0]);
        self::assertNull($calls[1]['args'][0]->getAttribute()->getRequired());
    }

    public function testEnsureUserIdentifierAttributeRejectsUnknownUserEntityClass(): void
    {
        $bootstrapper = new KeycloakBootstrapper(
            userIdentifierAttributeService: new class implements KeycloakUserIdentifierAttributeServiceInterface {
                #[\Override]
                public function ensureUserIdentifierAttribute(
                    string $realm,
                    EnsureUserIdentifierAttributeDto $dto
                ): void {
                }
            },
            httpClient: new TestKeycloakHttpClient(),
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
