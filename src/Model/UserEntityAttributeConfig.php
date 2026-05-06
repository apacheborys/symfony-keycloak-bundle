<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Model;

use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeRequiredDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use BackedEnum;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionProperty;
use Stringable;

final readonly class UserEntityAttributeConfig
{
    public const string LOCAL_USER_ID_PROPERTY = 'id';

    /** @var class-string */
    private string $className;

    private bool $hasRequiredConfiguration;

    private ?AttributeRequiredDto $required;

    /**
     * @param array{roles?: list<string>, scopes?: list<string>}|bool|null $required
     */
    public function __construct(
        string $className,
        private string $property,
        private ?string $attributeName = null,
        private ?string $jwtClaimName = null,
        private bool $createIfMissing = false,
        array|bool|null $required = null,
    ) {
        if (!class_exists($className)) {
            throw new InvalidArgumentException(
                sprintf('Configured user entity class "%s" does not exist.', $className)
            );
        }

        /** @var class-string $className */
        $this->className = $className;
        $this->hasRequiredConfiguration = $required !== null;
        $this->required = $this->normalizeRequired(required: $required);

        if ($this->property === '') {
            throw new InvalidArgumentException('The "property" configuration value cannot be empty.');
        }

        if ($this->attributeName !== null && trim($this->attributeName) === '') {
            throw new InvalidArgumentException('The "attributeName" configuration value cannot be blank.');
        }

        if ($this->jwtClaimName !== null && trim($this->jwtClaimName) === '') {
            throw new InvalidArgumentException('The "jwtClaimName" configuration value cannot be blank.');
        }

        if ($this->isLocalUserIdProperty()) {
            $this->assertSupportsLocalUserIdResolution();
        } else {
            $property = $this->resolveProperty();
            if ($property->isStatic()) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configured attribute property "%s" on "%s" cannot be static.',
                        $this->property,
                        $this->className,
                    )
                );
            }
        }
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getAttributeName(): string
    {
        return $this->attributeName ?? $this->property;
    }

    public function getJwtClaimName(): ?string
    {
        return $this->jwtClaimName;
    }

    /**
     * @return list<string>
     */
    public function getJwtClaimSearchNames(): array
    {
        return array_keys([
            $this->getAttributeName() => true,
            ...($this->jwtClaimName !== null ? [$this->jwtClaimName => true] : []),
        ]);
    }

    public function shouldCreateIfMissing(): bool
    {
        return $this->createIfMissing;
    }

    public function shouldExposeInJwt(): bool
    {
        return $this->jwtClaimName !== null;
    }

    public function hasRequiredConfiguration(): bool
    {
        return $this->hasRequiredConfiguration;
    }

    public function getRequired(): ?AttributeRequiredDto
    {
        return $this->required;
    }

    public function requiresBootstrapSync(): bool
    {
        return $this->createIfMissing || $this->shouldExposeInJwt() || $this->hasRequiredConfiguration;
    }

    public function buildEnsureAttributeDto(bool $forceCreateIfMissing = false): EnsureUserIdentifierAttributeDto
    {
        return new EnsureUserIdentifierAttributeDto(
            attributeName: $this->getAttributeName(),
            displayName: $this->buildAttributeDisplayName(),
            createIfMissing: $forceCreateIfMissing || $this->createIfMissing,
            exposeInJwt: $this->shouldExposeInJwt(),
            jwtClaimName: $this->jwtClaimName,
        );
    }

    public function isLocalUserIdProperty(): bool
    {
        return $this->property === self::LOCAL_USER_ID_PROPERTY;
    }

    public function resolveValue(object $localUser): string
    {
        if (!$localUser instanceof $this->className) {
            throw new LogicException(
                sprintf(
                    'Expected local user instance of "%s", got "%s".',
                    $this->className,
                    $localUser::class,
                )
            );
        }

        if ($this->isLocalUserIdProperty()) {
            if (!$localUser instanceof KeycloakUserInterface) {
                throw new LogicException(
                    sprintf(
                        'Configured identifier property "%s" on "%s" requires %s.',
                        $this->property,
                        $this->className,
                        KeycloakUserInterface::class,
                    )
                );
            }

            return $this->normalizeResolvedValue(value: $localUser->getId());
        }

        return $this->normalizeResolvedValue(value: $this->resolveProperty()->getValue($localUser));
    }

    private function resolveProperty(): ReflectionProperty
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);

        do {
            if ($reflection->hasProperty($this->property)) {
                return $reflection->getProperty($this->property);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection instanceof ReflectionClass);

        throw new InvalidArgumentException(
            sprintf(
                'Configured attribute property "%s" was not found on "%s".',
                $this->property,
                $this->className,
            )
        );
    }

    private function assertStringValue(string $value): string
    {
        if ($value === '') {
            throw new LogicException(
                sprintf(
                    'Configured attribute property "%s" on "%s" cannot resolve to an empty string.',
                    $this->property,
                    $this->className,
                )
            );
        }

        return $value;
    }

    private function assertSupportsLocalUserIdResolution(): void
    {
        if (is_a($this->className, KeycloakUserInterface::class, true)) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Configured attribute property "%s" on "%s" is reserved for %s::getId().',
                $this->property,
                $this->className,
                KeycloakUserInterface::class,
            )
        );
    }

    private function normalizeResolvedValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => $this->assertStringValue(value: $value),
            is_int($value), is_float($value) => (string) $value,
            $value instanceof BackedEnum => is_string($value->value)
                ? $this->assertStringValue(value: $value->value)
                : (string) $value->value,
            $value instanceof Stringable => $this->assertStringValue(value: (string) $value),
            default => throw new LogicException(
                sprintf(
                    'Configured attribute property "%s" on "%s" must resolve to a stringable scalar value.',
                    $this->property,
                    $this->className,
                )
            ),
        };
    }

    private function buildAttributeDisplayName(): string
    {
        return ucwords(str_replace(['-', '_', '.'], ' ', $this->getAttributeName()));
    }

    /**
     * @param array{roles?: list<string>, scopes?: list<string>}|bool|null $required
     */
    private function normalizeRequired(array|bool|null $required): ?AttributeRequiredDto
    {
        if ($required === null || $required === false) {
            return null;
        }

        if ($required === true) {
            return new AttributeRequiredDto();
        }

        $unknownKeys = array_diff(array_keys($required), ['roles', 'scopes']);
        if ($unknownKeys !== []) {
            throw new InvalidArgumentException(
                sprintf(
                    'Configured "required" options for attribute "%s" on "%s" contain unsupported keys: %s.',
                    $this->property,
                    $this->className,
                    implode(', ', $unknownKeys),
                )
            );
        }

        /** @var list<string> $roles */
        $roles = $this->normalizeRequiredStringList(
            fieldName: 'roles',
            value: $required['roles'] ?? [],
        );
        /** @var list<string> $scopes */
        $scopes = $this->normalizeRequiredStringList(
            fieldName: 'scopes',
            value: $required['scopes'] ?? [],
        );

        return new AttributeRequiredDto(
            roles: $roles,
            scopes: $scopes,
        );
    }

    /**
     * @return list<string>
     */
    private function normalizeRequiredStringList(string $fieldName, mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Configured "required.%s" for attribute "%s" on "%s" must be an array of strings.',
                    $fieldName,
                    $this->property,
                    $this->className,
                )
            );
        }

        $normalizedValues = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configured "required.%s" for attribute "%s" on "%s" must contain only non-empty strings.',
                        $fieldName,
                        $this->property,
                        $this->className,
                    )
                );
            }

            $normalizedValues[] = trim($item);
        }

        return $normalizedValues;
    }
}
