<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Security;

use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakException;
use Apacheborys\KeycloakPhpClient\Service\KeycloakJwtVerificationServiceInterface;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\Exception\KeycloakJwtAuthenticationException;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\Internal\CallsignValuePrefixer;
use Override;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class KeycloakJwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const string REQUEST_ATTRIBUTE_RAW_JWT = '_keycloak_bridge.jwt.raw';
    private const string REQUEST_ATTRIBUTE_PARSED_JWT = '_keycloak_bridge.jwt.parsed';

    /** @var list<string> */
    private readonly array $configuredIdentifierClaimNames;

    /**
     * @param iterable<UserEntityConfig> $userEntityConfigs
     */
    public function __construct(
        private readonly KeycloakJwtVerificationServiceInterface $jwtVerificationService,
        private readonly KeycloakClientConfig $keycloakClientConfig,
        iterable $userEntityConfigs,
        private readonly CallsignValuePrefixer $callsignValuePrefixer,
    ) {
        $configuredIdentifierClaimNames = [];
        foreach ($userEntityConfigs as $userEntityConfig) {
            foreach ($userEntityConfig->getUserIdentifierJwtClaimNames() as $claimName) {
                $configuredIdentifierClaimNames[$claimName] = true;
            }
        }

        $this->configuredIdentifierClaimNames = array_keys($configuredIdentifierClaimNames);
    }

    #[Override]
    public function supports(Request $request): ?bool
    {
        $rawJwt = $this->extractBearerToken(request: $request);
        if ($rawJwt === null) {
            return false;
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE_RAW_JWT, $rawJwt);

        try {
            $jwt = JsonWebToken::fromRawToken(rawToken: $rawJwt);
        } catch (\Throwable) {
            return true;
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE_PARSED_JWT, $jwt);

        return true;
    }

    #[Override]
    public function authenticate(Request $request): Passport
    {
        $rawJwt = $request->attributes->get(self::REQUEST_ATTRIBUTE_RAW_JWT);
        if (!is_string($rawJwt) || $rawJwt === '') {
            $rawJwt = $this->extractBearerToken(request: $request);
        }

        if (!is_string($rawJwt) || $rawJwt === '') {
            throw new CustomUserMessageAuthenticationException(message: 'JWT bearer token was not provided.');
        }

        $jwt = $request->attributes->get(self::REQUEST_ATTRIBUTE_PARSED_JWT);
        if (!$jwt instanceof JsonWebToken) {
            try {
                $jwt = JsonWebToken::fromRawToken(rawToken: $rawJwt);
            } catch (\Throwable $exception) {
                throw KeycloakJwtAuthenticationException::malformedToken(previous: $exception);
            }
        }

        if (!$this->isIssuerSupported(issuer: $jwt->getPayload()->getIss())) {
            throw KeycloakJwtAuthenticationException::unsupportedIssuer();
        }

        try {
            $verificationResult = $this->jwtVerificationService->verifyJwt(jwt: $rawJwt);
        } catch (KeycloakException $exception) {
            throw KeycloakJwtAuthenticationException::fromKeycloakException($exception);
        }

        if (!$verificationResult) {
            throw KeycloakJwtAuthenticationException::signatureValidationFailed();
        }

        $userIdentifier = $this->resolveUserIdentifier(jwt: $jwt);
        if ($userIdentifier === null) {
            throw KeycloakJwtAuthenticationException::identifierClaimMissing();
        }

        $roles = $this->extractRoles(jwt: $jwt);

        return new SelfValidatingPassport(
            new UserBadge(
                userIdentifier: $userIdentifier,
                userLoader: static fn (string $_): KeycloakJwtUser => new KeycloakJwtUser(
                    userIdentifier: $userIdentifier,
                    roles: $roles,
                    rawToken: $rawJwt,
                ),
            ),
        );
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $statusCode = $exception instanceof KeycloakJwtAuthenticationException
            ? $exception->getStatusCode()
            : Response::HTTP_UNAUTHORIZED;
        $message = $exception instanceof KeycloakJwtAuthenticationException
            ? $exception->getMessageKey()
            : 'Authentication failed.';
        $reason = $exception instanceof KeycloakJwtAuthenticationException
            ? $exception->getReasonCode()
            : $exception->getMessageKey();

        return new JsonResponse(
            data: [
                'message' => $message,
                'reason' => $reason,
            ],
            status: $statusCode,
        );
    }

    #[Override]
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            data: [
                'message' => 'Authentication required.',
            ],
            status: Response::HTTP_UNAUTHORIZED,
        );
    }

    /**
     * @return ?non-empty-string
     */
    private function resolveUserIdentifier(JsonWebToken $jwt): ?string
    {
        foreach ($this->configuredIdentifierClaimNames as $claimName) {
            if (!$jwt->getPayload()->hasClaim($claimName)) {
                continue;
            }

            $claimValue = $jwt->getPayload()->getClaim($claimName);
            if (is_string($claimValue)) {
                $normalizedClaimValue = trim($claimValue);
                if ($normalizedClaimValue !== '') {
                    $strippedClaimValue = trim($this->callsignValuePrefixer->strip($normalizedClaimValue));
                    if ($strippedClaimValue !== '') {
                        return $strippedClaimValue;
                    }
                }

                continue;
            }

            if (is_int($claimValue) || is_float($claimValue)) {
                return (string) $claimValue;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractRoles(JsonWebToken $jwt): array
    {
        $rawRoles = $jwt->getPayload()->getRealmAccess()['roles'];

        foreach ($jwt->getPayload()->getResourceAccess() as $resourceAccess) {
            foreach ($resourceAccess['roles'] as $resourceRole) {
                if (!is_string($resourceRole)) {
                    continue;
                }

                $rawRoles[] = $resourceRole;
            }
        }

        $roles = [];
        foreach ($rawRoles as $rawRole) {
            if (!is_string($rawRole)) {
                continue;
            }

            $normalizedRole = trim($rawRole);
            if ($normalizedRole === '') {
                continue;
            }

            $roles[$normalizedRole] = true;
        }

        if ($roles === []) {
            $roles['ROLE_USER'] = true;
        }

        return array_keys($roles);
    }

    private function isIssuerSupported(string $issuer): bool
    {
        $issuerParts = parse_url(url: $issuer);
        $baseUrlParts = parse_url(url: $this->keycloakClientConfig->getBaseUrl());

        if (!is_array($issuerParts) || !is_array($baseUrlParts)) {
            return false;
        }

        $issuerScheme = strtolower((string) ($issuerParts['scheme'] ?? ''));
        $baseScheme = strtolower((string) ($baseUrlParts['scheme'] ?? ''));
        if ($issuerScheme === '' || $baseScheme === '' || $issuerScheme !== $baseScheme) {
            return false;
        }

        $issuerHost = strtolower((string) ($issuerParts['host'] ?? ''));
        $baseHost = strtolower((string) ($baseUrlParts['host'] ?? ''));
        if ($issuerHost === '' || $baseHost === '' || $issuerHost !== $baseHost) {
            return false;
        }

        $issuerPort = $issuerParts['port'] ?? $this->resolveDefaultPort(scheme: $issuerScheme);
        $basePort = $baseUrlParts['port'] ?? $this->resolveDefaultPort(scheme: $baseScheme);
        if ($issuerPort !== $basePort) {
            return false;
        }

        $issuerPath = trim((string) ($issuerParts['path'] ?? ''), '/');
        $basePath = trim((string) ($baseUrlParts['path'] ?? ''), '/');

        if ($basePath !== '') {
            if ($issuerPath === $basePath) {
                return false;
            }

            if (!str_starts_with($issuerPath . '/', $basePath . '/')) {
                return false;
            }
        }

        $segments = explode(separator: '/', string: $issuerPath);
        foreach ($segments as $index => $segment) {
            if ($segment !== 'realms') {
                continue;
            }

            $realm = $segments[$index + 1] ?? null;
            if (is_string($realm) && $realm !== '') {
                return true;
            }
        }

        return false;
    }

    private function resolveDefaultPort(string $scheme): ?int
    {
        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (!is_string($header) || $header === '') {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim($matches[1]);
        if ($token === '') {
            return null;
        }

        return $token;
    }
}
