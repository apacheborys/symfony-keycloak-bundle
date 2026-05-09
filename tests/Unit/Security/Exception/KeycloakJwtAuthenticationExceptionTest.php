<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Security\Exception;

use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthorizationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakErrorContext;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakRateLimitException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakServerException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\Exception\KeycloakJwtAuthenticationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class KeycloakJwtAuthenticationExceptionTest extends TestCase
{
    public function testTokenNotProvidedFactoryReturnsSafeAuthenticationFailure(): void
    {
        $exception = KeycloakJwtAuthenticationException::tokenNotProvided();

        self::assertSame('JWT bearer token was not provided.', $exception->getMessageKey());
        self::assertSame('JWT bearer token was not provided.', $exception->getMessage());
        self::assertSame(KeycloakJwtAuthenticationException::REASON_TOKEN_NOT_PROVIDED, $exception->getReasonCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
    }

    public function testMalformedTokenFactoryReturnsSafeAuthenticationFailure(): void
    {
        $previous = new \RuntimeException(
            'Authorization: Bearer header.payload.signature access_token=secret refresh_token=secret'
        );
        $exception = KeycloakJwtAuthenticationException::malformedToken(previous: $previous);

        self::assertSame('Malformed JWT token.', $exception->getMessageKey());
        self::assertSame('Malformed JWT token.', $exception->getMessage());
        self::assertSame(KeycloakJwtAuthenticationException::REASON_MALFORMED_TOKEN, $exception->getReasonCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertStringNotContainsString('Authorization', $exception->getMessageKey());
        self::assertStringNotContainsString('Bearer', $exception->getMessageKey());
        self::assertStringNotContainsString('access_token', $exception->getMessageKey());
        self::assertStringNotContainsString('refresh_token', $exception->getMessageKey());
        self::assertStringNotContainsString('header.payload.signature', $exception->getMessageKey());
    }

    #[DataProvider('keycloakExceptionMappingProvider')]
    public function testFromKeycloakExceptionMapsToSafeAuthenticationFailure(
        KeycloakException $keycloakException,
        string $expectedMessageKey,
        string $expectedReasonCode,
        int $expectedStatusCode,
    ): void {
        $exception = KeycloakJwtAuthenticationException::fromKeycloakException($keycloakException);

        self::assertSame($expectedMessageKey, $exception->getMessageKey());
        self::assertSame($expectedMessageKey, $exception->getMessage());
        self::assertSame($expectedReasonCode, $exception->getReasonCode());
        self::assertSame($expectedStatusCode, $exception->getStatusCode());
        self::assertSame($keycloakException, $exception->getPrevious());
        self::assertStringNotContainsString('Authorization', $exception->getMessageKey());
        self::assertStringNotContainsString('Bearer', $exception->getMessageKey());
        self::assertStringNotContainsString('access_token', $exception->getMessageKey());
        self::assertStringNotContainsString('refresh_token', $exception->getMessageKey());
        self::assertStringNotContainsString('client_secret', $exception->getMessageKey());
        self::assertStringNotContainsString('super-secret-token', $exception->getMessageKey());
        self::assertStringNotContainsString('super-secret-body', $exception->getMessageKey());
    }

    /**
     * @return iterable<string, array{0: KeycloakException, 1: string, 2: string, 3: int}>
     */
    public static function keycloakExceptionMappingProvider(): iterable
    {
        $context = new KeycloakErrorContext(
            method: 'GET',
            uri: 'https://keycloak.example.test/protocol/openid-connect/certs?client_secret=secret',
            statusCode: 503,
            responseBody: 'super-secret-body',
            keycloakError: 'Bearer super-secret-token',
            keycloakErrorDescription: 'Authorization: Bearer super-secret-token',
            correlationId: 'access_token refresh_token client_secret',
        );

        yield 'authentication' => [
            new KeycloakAuthenticationException($context),
            'Keycloak authentication failed.',
            KeycloakJwtAuthenticationException::REASON_KEYCLOAK_AUTHENTICATION_FAILED,
            Response::HTTP_UNAUTHORIZED,
        ];

        yield 'authorization' => [
            new KeycloakAuthorizationException($context),
            'Keycloak authorization failed.',
            KeycloakJwtAuthenticationException::REASON_KEYCLOAK_AUTHORIZATION_FAILED,
            Response::HTTP_FORBIDDEN,
        ];

        yield 'rate limit' => [
            new KeycloakRateLimitException($context),
            'Keycloak rate limit exceeded.',
            KeycloakJwtAuthenticationException::REASON_KEYCLOAK_RATE_LIMITED,
            Response::HTTP_TOO_MANY_REQUESTS,
        ];

        yield 'invalid response' => [
            new KeycloakInvalidResponseException($context),
            'Keycloak returned an invalid response.',
            KeycloakJwtAuthenticationException::REASON_KEYCLOAK_INVALID_RESPONSE,
            Response::HTTP_BAD_GATEWAY,
        ];

        yield 'transport' => [
            new KeycloakTransportException($context),
            'Keycloak is temporarily unavailable.',
            KeycloakJwtAuthenticationException::REASON_KEYCLOAK_UNAVAILABLE,
            Response::HTTP_SERVICE_UNAVAILABLE,
        ];

        yield 'server' => [
            new KeycloakServerException($context),
            'Keycloak is temporarily unavailable.',
            KeycloakJwtAuthenticationException::REASON_KEYCLOAK_UNAVAILABLE,
            Response::HTTP_SERVICE_UNAVAILABLE,
        ];
    }
}
