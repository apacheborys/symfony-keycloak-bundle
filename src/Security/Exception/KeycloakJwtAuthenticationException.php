<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Security\Exception;

use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthorizationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakRateLimitException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakServerException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Throwable;

final class KeycloakJwtAuthenticationException extends AuthenticationException
{
    public const string REASON_MALFORMED_TOKEN = 'malformed_token';
    public const string REASON_UNSUPPORTED_ISSUER = 'unsupported_issuer';
    public const string REASON_SIGNATURE_VALIDATION_FAILED = 'signature_validation_failed';
    public const string REASON_IDENTIFIER_CLAIM_MISSING = 'identifier_claim_missing';
    public const string REASON_KEYCLOAK_AUTHENTICATION_FAILED = 'keycloak_authentication_failed';
    public const string REASON_KEYCLOAK_AUTHORIZATION_FAILED = 'keycloak_authorization_failed';
    public const string REASON_KEYCLOAK_UNAVAILABLE = 'keycloak_unavailable';
    public const string REASON_KEYCLOAK_RATE_LIMITED = 'keycloak_rate_limited';
    public const string REASON_KEYCLOAK_INVALID_RESPONSE = 'keycloak_invalid_response';

    private string $messageKey;
    private string $reasonCode;
    private int $statusCode;

    public function __construct(
        string $messageKey,
        string $reasonCode,
        int $statusCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct(message: $messageKey, previous: $previous);

        $this->messageKey = $messageKey;
        $this->reasonCode = $reasonCode;
        $this->statusCode = $statusCode;
    }

    public static function malformedToken(?Throwable $previous = null): self
    {
        return new self(
            messageKey: 'Malformed JWT token.',
            reasonCode: self::REASON_MALFORMED_TOKEN,
            statusCode: Response::HTTP_UNAUTHORIZED,
            previous: $previous,
        );
    }

    public static function unsupportedIssuer(): self
    {
        return new self(
            messageKey: 'JWT issuer is not supported.',
            reasonCode: self::REASON_UNSUPPORTED_ISSUER,
            statusCode: Response::HTTP_UNAUTHORIZED,
        );
    }

    public static function signatureValidationFailed(): self
    {
        return new self(
            messageKey: 'JWT signature validation failed.',
            reasonCode: self::REASON_SIGNATURE_VALIDATION_FAILED,
            statusCode: Response::HTTP_UNAUTHORIZED,
        );
    }

    public static function identifierClaimMissing(): self
    {
        return new self(
            messageKey: 'Configured JWT user identifier attribute is missing.',
            reasonCode: self::REASON_IDENTIFIER_CLAIM_MISSING,
            statusCode: Response::HTTP_UNAUTHORIZED,
        );
    }

    public static function fromKeycloakException(KeycloakException $exception): self
    {
        return match (true) {
            $exception instanceof KeycloakAuthenticationException => new self(
                messageKey: 'Keycloak authentication failed.',
                reasonCode: self::REASON_KEYCLOAK_AUTHENTICATION_FAILED,
                statusCode: Response::HTTP_UNAUTHORIZED,
                previous: $exception,
            ),
            $exception instanceof KeycloakAuthorizationException => new self(
                messageKey: 'Keycloak authorization failed.',
                reasonCode: self::REASON_KEYCLOAK_AUTHORIZATION_FAILED,
                statusCode: Response::HTTP_FORBIDDEN,
                previous: $exception,
            ),
            $exception instanceof KeycloakRateLimitException => new self(
                messageKey: 'Keycloak rate limit exceeded.',
                reasonCode: self::REASON_KEYCLOAK_RATE_LIMITED,
                statusCode: Response::HTTP_TOO_MANY_REQUESTS,
                previous: $exception,
            ),
            $exception instanceof KeycloakInvalidResponseException => new self(
                messageKey: 'Keycloak returned an invalid response.',
                reasonCode: self::REASON_KEYCLOAK_INVALID_RESPONSE,
                statusCode: Response::HTTP_BAD_GATEWAY,
                previous: $exception,
            ),
            $exception instanceof KeycloakTransportException,
            $exception instanceof KeycloakServerException => new self(
                messageKey: 'Keycloak is temporarily unavailable.',
                reasonCode: self::REASON_KEYCLOAK_UNAVAILABLE,
                statusCode: Response::HTTP_SERVICE_UNAVAILABLE,
                previous: $exception,
            ),
            default => new self(
                messageKey: 'Keycloak is temporarily unavailable.',
                reasonCode: self::REASON_KEYCLOAK_UNAVAILABLE,
                statusCode: Response::HTTP_SERVICE_UNAVAILABLE,
                previous: $exception,
            ),
        };
    }

    #[Override]
    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    #[Override]
    public function __serialize(): array
    {
        return [
            $this->messageKey,
            $this->reasonCode,
            $this->statusCode,
            parent::__serialize(),
        ];
    }

    /**
     * @param array{0: string, 1: string, 2: int, 3: array<mixed>} $data
     */
    #[Override]
    public function __unserialize(array $data): void
    {
        [
            $this->messageKey,
            $this->reasonCode,
            $this->statusCode,
            $parentData,
        ] = $data;

        parent::__unserialize($parentData);
    }
}
