<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Security;

use Apacheborys\KeycloakPhpClient\Exception\KeycloakErrorContext;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakRateLimitException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakServerException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use Apacheborys\KeycloakPhpClient\Service\KeycloakJwtVerificationServiceInterface;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\Exception\KeycloakJwtAuthenticationException;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtAuthenticator;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\Internal\CallsignValuePrefixer;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function testSupportsReturnsTrueForJwtFromUnexpectedIssuer(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(issuer: 'https://other.example.test/realms/users-realm');

        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));
    }

    public function testAuthenticateBuildsSymfonyUserFromJwtClaims(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(
            issuer: 'https://example.test/realms/users-realm',
            preferredUsername: 'alice@example.test',
            realmRoles: ['bridge.payment.ROLE_USER.svc'],
            accountRoles: ['manage-account'],
            additionalPayloadClaims: ['external_user_id' => 'bridge.some-external-user-id'],
        );
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);
        $user = $badge->getUser();

        self::assertInstanceOf(KeycloakJwtUser::class, $user);
        self::assertSame('some-external-user-id', $user->getUserIdentifier());
        self::assertSame(['bridge.payment.ROLE_USER.svc', 'manage-account'], $user->getRoles());
    }

    public function testAuthenticateBuildsSymfonyUserFromConfiguredJwtClaimName(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(
            issuer: 'https://example.test/realms/users-realm',
            preferredUsername: 'alice@example.test',
            additionalPayloadClaims: ['external_user_id_test' => 'bridge.alias-user-id'],
        );
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);
        $user = $badge->getUser();

        self::assertInstanceOf(KeycloakJwtUser::class, $user);
        self::assertSame('alias-user-id', $user->getUserIdentifier());
    }

    public function testAuthenticateThrowsWhenConfiguredIdentifierClaimIsMissing(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(
            issuer: 'https://example.test/realms/users-realm',
            preferredUsername: 'alice@example.test',
        );
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));

        self::assertAuthenticationFailure(
            authenticator: $authenticator,
            request: $request,
            expectedMessage: 'Configured JWT user identifier attribute is missing.',
            expectedReason: KeycloakJwtAuthenticationException::REASON_IDENTIFIER_CLAIM_MISSING,
            expectedStatusCode: Response::HTTP_UNAUTHORIZED,
        );
    }

    public function testAuthenticateThrowsWhenJwtVerificationFails(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: false, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(
            issuer: 'https://example.test/realms/users-realm',
            additionalPayloadClaims: ['external_user_id' => 'bridge.some-external-user-id'],
        );
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));

        self::assertAuthenticationFailure(
            authenticator: $authenticator,
            request: $request,
            expectedMessage: 'JWT signature validation failed.',
            expectedReason: KeycloakJwtAuthenticationException::REASON_SIGNATURE_VALIDATION_FAILED,
            expectedStatusCode: Response::HTTP_UNAUTHORIZED,
        );
    }

    public function testAuthenticateThrowsWhenJwtIssuerIsUnsupported(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $jwt = self::buildJwt(
            issuer: 'https://other.example.test/realms/users-realm',
            additionalPayloadClaims: ['external_user_id' => 'bridge.some-external-user-id'],
        );
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));

        self::assertAuthenticationFailure(
            authenticator: $authenticator,
            request: $request,
            expectedMessage: 'JWT issuer is not supported.',
            expectedReason: KeycloakJwtAuthenticationException::REASON_UNSUPPORTED_ISSUER,
            expectedStatusCode: Response::HTTP_UNAUTHORIZED,
        );
    }

    public function testAuthenticateThrowsWhenJwtIsMalformed(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);

        self::assertTrue($authenticator->supports($request));

        self::assertAuthenticationFailure(
            authenticator: $authenticator,
            request: $request,
            expectedMessage: 'Malformed JWT token.',
            expectedReason: KeycloakJwtAuthenticationException::REASON_MALFORMED_TOKEN,
            expectedStatusCode: Response::HTTP_UNAUTHORIZED,
        );
    }

    public function testAuthenticateTranslatesKeycloakTransportExceptionIntoControlledFailure(): void
    {
        $authenticator = $this->createAuthenticator(
            verificationResult: new KeycloakTransportException(
                new KeycloakErrorContext(
                    method: 'GET',
                    uri: 'https://example.test/protocol/openid-connect/certs?client_secret=secret',
                    statusCode: 503,
                    responseBody: 'sensitive response body',
                ),
            ),
            baseUrl: 'https://example.test',
        );
        $jwt = self::buildJwt(
            issuer: 'https://example.test/realms/users-realm',
            additionalPayloadClaims: ['external_user_id' => 'bridge.some-external-user-id'],
        );
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        self::assertTrue($authenticator->supports($request));

        self::assertAuthenticationFailure(
            authenticator: $authenticator,
            request: $request,
            expectedMessage: 'Keycloak is temporarily unavailable.',
            expectedReason: KeycloakJwtAuthenticationException::REASON_KEYCLOAK_UNAVAILABLE,
            expectedStatusCode: Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    public function testOnAuthenticationFailureUsesBridgeExceptionStatusAndReasonCode(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $response = $authenticator->onAuthenticationFailure(
            new Request(),
            KeycloakJwtAuthenticationException::fromKeycloakException(
                new KeycloakTransportException(
                    new KeycloakErrorContext(
                        method: 'GET',
                        uri: 'https://example.test/protocol/openid-connect/certs?client_secret=secret',
                        statusCode: 503,
                        responseBody: 'sensitive response body',
                    ),
                ),
            ),
        );

        self::assertNotNull($response);
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame(
            [
                'message' => 'Authentication failed.',
                'reason' => KeycloakJwtAuthenticationException::REASON_KEYCLOAK_UNAVAILABLE,
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testOnAuthenticationFailureReturns503ForKeycloakServerException(): void
    {
        $response = $this->authenticateAndRenderFailureResponse(
            new KeycloakServerException(
                new KeycloakErrorContext(
                    method: 'GET',
                    uri: 'https://example.test/protocol/openid-connect/certs?client_secret=secret',
                    statusCode: 503,
                    responseBody: 'sensitive response body',
                ),
            ),
        );

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame(
            [
                'message' => 'Authentication failed.',
                'reason' => KeycloakJwtAuthenticationException::REASON_KEYCLOAK_UNAVAILABLE,
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testOnAuthenticationFailureReturns429ForKeycloakRateLimitException(): void
    {
        $response = $this->authenticateAndRenderFailureResponse(
            new KeycloakRateLimitException(
                new KeycloakErrorContext(
                    method: 'GET',
                    uri: 'https://example.test/protocol/openid-connect/certs?client_secret=secret',
                    statusCode: 429,
                    responseBody: 'sensitive response body',
                ),
            ),
        );

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame(
            [
                'message' => 'Authentication failed.',
                'reason' => KeycloakJwtAuthenticationException::REASON_KEYCLOAK_RATE_LIMITED,
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testOnAuthenticationFailureReturns502ForKeycloakInvalidResponseException(): void
    {
        $response = $this->authenticateAndRenderFailureResponse(
            new KeycloakInvalidResponseException(
                new KeycloakErrorContext(
                    method: 'GET',
                    uri: 'https://example.test/protocol/openid-connect/certs?client_secret=secret',
                    statusCode: 502,
                    responseBody: 'sensitive response body',
                ),
            ),
        );

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame(
            [
                'message' => 'Authentication failed.',
                'reason' => KeycloakJwtAuthenticationException::REASON_KEYCLOAK_INVALID_RESPONSE,
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAuthenticationFailureResponseDoesNotIncludeRawJwt(): void
    {
        $authenticator = $this->createAuthenticator(verificationResult: true, baseUrl: 'https://example.test');
        $rawJwt = 'header.payload.signature';
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $rawJwt]);

        self::assertTrue($authenticator->supports($request));

        $response = $authenticator->onAuthenticationFailure(
            $request,
            self::catchAuthenticationFailure(
                authenticator: $authenticator,
                request: $request,
            ),
        );

        self::assertNotNull($response);
        $content = (string) $response->getContent();

        self::assertStringNotContainsString($rawJwt, $content);
        self::assertStringNotContainsString('Bearer', $content);
    }

    private function createAuthenticator(bool|\Throwable $verificationResult, string $baseUrl): KeycloakJwtAuthenticator
    {
        return new KeycloakJwtAuthenticator(
            jwtVerificationService: new class ($verificationResult) implements KeycloakJwtVerificationServiceInterface {
                public function __construct(
                    private readonly bool|\Throwable $verificationResult,
                ) {
                }

                public function verifyJwt(string $jwt): bool
                {
                    if ($this->verificationResult instanceof \Throwable) {
                        throw $this->verificationResult;
                    }

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
            userEntityConfigs: [
                new UserEntityConfig(
                    realm: 'users-realm',
                    className: LocalUser::class,
                    attributesMap: [
                        [
                            'property' => 'id',
                            'attribute_name' => 'external_user_id',
                            'jwt_claim_name' => 'external_user_id_test',
                            'create_if_missing' => true,
                        ],
                    ],
                ),
            ],
            callsignValuePrefixer: new CallsignValuePrefixer('bridge'),
        );
    }

    /**
     * @param list<string> $realmRoles
     * @param list<string> $accountRoles
     * @param array<string, mixed> $additionalPayloadClaims
     */
    private static function buildJwt(
        string $issuer,
        string $preferredUsername = 'local-user',
        array $realmRoles = ['ROLE_USER'],
        array $accountRoles = ['view-profile'],
        array $additionalPayloadClaims = [],
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
        $payload = [...$payload, ...$additionalPayloadClaims];

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

    private static function assertAuthenticationFailure(
        KeycloakJwtAuthenticator $authenticator,
        Request $request,
        string $expectedMessage,
        string $expectedReason,
        int $expectedStatusCode,
    ): void {
        try {
            $authenticator->authenticate($request);
            self::fail('Expected KeycloakJwtAuthenticationException to be thrown.');
        } catch (KeycloakJwtAuthenticationException $exception) {
            self::assertSame($expectedMessage, $exception->getMessageKey());
            self::assertSame($expectedReason, $exception->getReasonCode());
            self::assertSame($expectedStatusCode, $exception->getStatusCode());
        }
    }

    private function authenticateAndRenderFailureResponse(\Throwable $verificationResult): Response
    {
        $authenticator = $this->createAuthenticator(
            verificationResult: $verificationResult,
            baseUrl: 'https://example.test',
        );
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::buildJwt(
            issuer: 'https://example.test/realms/users-realm',
            additionalPayloadClaims: ['external_user_id' => 'bridge.some-external-user-id'],
        )]);

        self::assertTrue($authenticator->supports($request));

        $response = $authenticator->onAuthenticationFailure(
            $request,
            self::catchAuthenticationFailure(
                authenticator: $authenticator,
                request: $request,
            ),
        );

        self::assertNotNull($response);

        return $response;
    }

    private static function catchAuthenticationFailure(
        KeycloakJwtAuthenticator $authenticator,
        Request $request,
    ): KeycloakJwtAuthenticationException {
        try {
            $authenticator->authenticate($request);
        } catch (KeycloakJwtAuthenticationException $exception) {
            return $exception;
        }

        self::fail('Expected KeycloakJwtAuthenticationException to be thrown.');
    }
}
