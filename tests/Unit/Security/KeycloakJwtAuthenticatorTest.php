<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Security;

use Apacheborys\KeycloakPhpClient\Service\KeycloakJwtVerificationServiceInterface;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtAuthenticator;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class KeycloakJwtAuthenticatorTest extends TestCase
{
    public function testSupportsReturnsTrueForJwtFromConfiguredKeycloakIssuer(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(issuer: 'https://example.test/realms/users-realm');

        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));
    }

    public function testSupportsReturnsFalseForJwtFromUnexpectedIssuer(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(issuer: 'https://other.example.test/realms/users-realm');

        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertFalse($authenticator->supports($request));
    }

    public function testAuthenticateBuildsSymfonyUserFromJwtClaims(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(
            issuer: 'https://example.test/realms/users-realm',
            preferredUsername: 'alice',
            realmRoles: ['payment.ROLE_USER.svc'],
            accountRoles: ['manage-account'],
        );
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);
        $user = $badge->getUser();

        self::assertInstanceOf(KeycloakJwtUser::class, $user);
        self::assertSame('alice', $user->getUserIdentifier());
        self::assertSame(['payment.ROLE_USER.svc', 'manage-account'], $user->getRoles());
    }

    public function testAuthenticateThrowsWhenJwtVerificationFails(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: false, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(issuer: 'https://example.test/realms/users-realm');
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('JWT signature validation failed.');
        $authenticator->authenticate($request);
    }

    private function createAuthenticator(bool $verificationResult, string $baseUrl): KeycloakJwtAuthenticator
    {
        return new KeycloakJwtAuthenticator(
            jwtVerificationService: new class ($verificationResult) implements KeycloakJwtVerificationServiceInterface {
                public function __construct(
                    private readonly bool $verificationResult,
                ) {
                }

                public function verifyJwt(string $jwt): bool
                {
                    return $this->verificationResult;
                }
            },
            keycloakClientConfig: new KeycloakClientConfig(
                baseUrl: $baseUrl,
                clientRealm: 'master',
                clientId: 'bridge-client',
                clientSecret: 'bridge-secret',
                realmListTtl: 30,
            ),
        );
    }

    /**
     * @param list<string> $realmRoles
     * @param list<string> $accountRoles
     */
    private static function buildJwt(
        string $issuer,
        string $preferredUsername = 'local-user',
        array $realmRoles = ['ROLE_USER'],
        array $accountRoles = ['view-profile'],
    ): string {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-key-id',
        ];

        $now = time();
        $payload = [
            'exp' => $now + 3600,
            'iat' => $now - 60,
            'jti' => '6ed6bdad-4a6f-4e4e-a2b9-8ebf6f354be1',
            'iss' => $issuer,
            'aud' => ['account'],
            'sub' => '8fb97efd-f1f8-4728-a8e7-84037d2bc688',
            'typ' => 'Bearer',
            'azp' => 'bridge-client',
            'acr' => 1,
            'realm_access' => [
                'roles' => $realmRoles,
            ],
            'resource_access' => [
                'account' => [
                    'roles' => $accountRoles,
                ],
            ],
            'scope' => 'openid profile email',
            'email_verified' => true,
            'preferred_username' => $preferredUsername,
            'clientHost' => '127.0.0.1',
            'clientAddress' => '127.0.0.1',
            'client_id' => 'bridge-client',
        ];

        return self::encodeSegment(data: $header)
            . '.'
            . self::encodeSegment(data: $payload)
            . '.signature';
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encodeSegment(array $data): string
    {
        $encoded = base64_encode((string) json_encode($data, JSON_THROW_ON_ERROR));

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }
}
