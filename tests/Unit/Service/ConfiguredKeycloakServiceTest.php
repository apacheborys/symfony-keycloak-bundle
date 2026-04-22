<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Model\KeycloakUserAccess;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\ConfiguredKeycloakService;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ConfiguredKeycloakServiceTest extends TestCase
{
    public function testCreateUserEnsuresConfiguredIdentifierAttributeBeforeDelegating(): void
    {
        $user = new LocalUser();
        $password = new PasswordDto(plainPassword: 'secret-password');
        $createdUser = $this->buildKeycloakUser();
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'localIdentifier',
            attributeName: 'local-user-id',
            jwtClaimName: 'local_user_id',
            exposeInJwt: true,
            createIfMissing: true,
        );

        $ensured = false;
        $inner = $this->createMock(KeycloakServiceInterface::class);
        $inner
            ->expects(self::once())
            ->method('ensureUserIdentifierAttribute')
            ->with(
                $user,
                self::callback(static function ($dto): bool {
                    self::assertSame('local-user-id', $dto->getAttributeName());
                    self::assertSame('local_user_id', $dto->getJwtClaimName());
                    self::assertTrue($dto->shouldExposeInJwt());
                    self::assertTrue($dto->shouldCreateIfMissing());

                    return true;
                })
            )
            ->willReturnCallback(static function () use (&$ensured): void {
                $ensured = true;
            });
        $inner
            ->expects(self::once())
            ->method('createUser')
            ->with($user, $password)
            ->willReturnCallback(static function () use (&$ensured, $createdUser): KeycloakUser {
                self::assertTrue($ensured);

                return $createdUser;
            });

        $service = new ConfiguredKeycloakService($inner, [$config]);

        self::assertSame($createdUser, $service->createUser($user, $password));
    }

    public function testLoginUserEnsuresConfiguredIdentifierAttributeWhenJwtExposureIsEnabled(): void
    {
        $user = new LocalUser();
        $tokenResponse = $this->buildTokenResponse();
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'localIdentifier',
            exposeInJwt: true,
        );

        $ensured = false;
        $inner = $this->createMock(KeycloakServiceInterface::class);
        $inner
            ->expects(self::once())
            ->method('ensureUserIdentifierAttribute')
            ->with($user, self::anything())
            ->willReturnCallback(static function () use (&$ensured): void {
                $ensured = true;
            });
        $inner
            ->expects(self::once())
            ->method('loginUser')
            ->with($user, 'secret-password')
            ->willReturnCallback(static function () use (&$ensured, $tokenResponse): OidcTokenResponseDto {
                self::assertTrue($ensured);

                return $tokenResponse;
            });

        $service = new ConfiguredKeycloakService($inner, [$config]);

        self::assertSame($tokenResponse, $service->loginUser($user, 'secret-password'));
    }

    public function testCreateUserSkipsEnsureWhenAttributeManagementIsDisabled(): void
    {
        $user = new LocalUser();
        $password = new PasswordDto(plainPassword: 'secret-password');
        $createdUser = $this->buildKeycloakUser();
        $config = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'localIdentifier',
        );

        $inner = $this->createMock(KeycloakServiceInterface::class);
        $inner->expects(self::never())->method('ensureUserIdentifierAttribute');
        $inner
            ->expects(self::once())
            ->method('createUser')
            ->with($user, $password)
            ->willReturn($createdUser);

        $service = new ConfiguredKeycloakService($inner, [$config]);

        self::assertSame($createdUser, $service->createUser($user, $password));
    }

    private function buildKeycloakUser(): KeycloakUser
    {
        return new KeycloakUser(
            id: Uuid::fromString('58f5b67f-bcf4-4d12-86a3-a54f7704f326'),
            username: 'local-username',
            firstName: 'Local',
            lastName: 'User',
            email: 'local@example.test',
            emailVerified: true,
            createdTimestamp: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            enabled: true,
            totp: false,
            disableableCredentialTypes: [],
            requiredActions: [],
            notBefore: 0,
            access: new KeycloakUserAccess(
                manageGroupMembership: true,
                view: true,
                mapRoles: true,
                impersonate: false,
                manage: true,
            ),
            roles: [],
            attributes: [],
        );
    }

    private function buildTokenResponse(): OidcTokenResponseDto
    {
        $header = $this->base64UrlEncode('{"alg":"RS256","typ":"JWT","kid":"test-key"}');
        $payload = $this->base64UrlEncode(
            json_encode(
                [
                    'exp' => 1_900_000_000,
                    'iat' => 1_899_999_000,
                    'jti' => '7ae8eba6-f101-45a5-9f9e-a77032410cc5',
                    'iss' => 'https://example.test/realms/users-realm',
                    'aud' => ['account'],
                    'sub' => '58f5b67f-bcf4-4d12-86a3-a54f7704f326',
                    'typ' => 'Bearer',
                    'azp' => 'bridge-client',
                    'acr' => 1,
                    'realm_access' => ['roles' => ['ROLE_USER']],
                    'resource_access' => [
                        'account' => ['roles' => ['manage-account']],
                        'backend' => ['roles' => ['service-role']],
                    ],
                    'scope' => 'openid profile email',
                    'email_verified' => true,
                    'preferred_username' => 'local-username',
                    'clientHost' => '127.0.0.1',
                    'clientAddress' => '127.0.0.1',
                    'client_id' => 'bridge-client',
                ],
                JSON_THROW_ON_ERROR
            )
        );

        return OidcTokenResponseDto::fromArray(
            [
                'access_token' => $header . '.' . $payload . '.signature',
                'expires_in' => 300,
                'refresh_expires_in' => 600,
                'token_type' => 'Bearer',
                'not-before-policy' => 0,
                'scope' => 'openid profile email',
            ]
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
