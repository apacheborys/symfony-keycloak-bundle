<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Model;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use BackedEnum;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionProperty;
use Stringable;

final readonly class UserEntityAttributeConfig
{
    /** @var class-string */
    private string $className;

    public function __construct(
        string $className,
        private string $property,
        private ?string $attributeName = null,
        private ?string $jwtClaimName = null,
        private bool $createIfMissing = false,
    ) {
        if (!class_exists($className)) {
            throw new InvalidArgumentException(
                sprintf('Configured user entity class "%s" does not exist.', $className)
            );
        }

        /** @var class-string $className */
        $this->className = $className;

        if ($this->property === '') {
            throw new InvalidArgumentException('The "property" configuration value cannot be empty.');
        }

        if ($this->attributeName !== null && trim($this->attributeName) === '') {
            throw new InvalidArgumentException('The "attributeName" configuration value cannot be blank.');
        }

        if ($this->jwtClaimName !== null && trim($this->jwtClaimName) === '') {
            throw new InvalidArgumentException('The "jwtClaimName" configuration value cannot be blank.');
        }

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

    public function shouldEnsureAttribute(): bool
    {
        return $this->createIfMissing || $this->shouldExposeInJwt();
    }

    public function buildEnsureAttributeDto(): EnsureUserIdentifierAttributeDto
    {
        return new EnsureUserIdentifierAttributeDto(
            attributeName: $this->getAttributeName(),
            displayName: $this->buildAttributeDisplayName(),
            createIfMissing: $this->createIfMissing,
            exposeInJwt: $this->shouldExposeInJwt(),
            jwtClaimName: $this->jwtClaimName,
        );
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

        $value = $this->resolveProperty()->getValue($localUser);

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

    private function buildAttributeDisplayName(): string
    {
        return ucwords(str_replace(['-', '_', '.'], ' ', $this->getAttributeName()));
    }
}
