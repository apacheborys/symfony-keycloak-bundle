<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Model;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use BackedEnum;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionProperty;
use Stringable;

final readonly class UserEntityConfig
{
    /** @var class-string */
    private string $className;

    public function __construct(
        private string $realm,
        string $className,
        private string $userIdentifierField,
        private string $rolePrefix = '',
        private string $roleSuffix = '',
        private string $mapper = LocalEntityMapper::class,
        private ?string $attributeName = null,
        private ?string $jwtClaimName = null,
        private bool $exposeInJwt = false,
        private bool $createIfMissing = false,
    ) {
        if (!class_exists($className)) {
            throw new InvalidArgumentException(
                sprintf('Configured user entity class "%s" does not exist.', $className)
            );
        }

        /** @var class-string $className */
        $this->className = $className;

        if ($this->userIdentifierField === '') {
            throw new InvalidArgumentException('The "userIdentifierField" configuration value cannot be empty.');
        }

        if ($this->attributeName !== null && trim($this->attributeName) === '') {
            throw new InvalidArgumentException('The "attributeName" configuration value cannot be blank.');
        }

        if ($this->jwtClaimName !== null && trim($this->jwtClaimName) === '') {
            throw new InvalidArgumentException('The "jwtClaimName" configuration value cannot be blank.');
        }

        $identifierProperty = $this->resolveIdentifierProperty();
        if ($identifierProperty->isStatic()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Configured user identifier field "%s" on "%s" cannot be static.',
                    $this->userIdentifierField,
                    $this->className
                )
            );
        }
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getUserIdentifierField(): string
    {
        return $this->userIdentifierField;
    }

    public function getRolePrefix(): string
    {
        return $this->rolePrefix;
    }

    public function getRoleSuffix(): string
    {
        return $this->roleSuffix;
    }

    public function getMapper(): string
    {
        return $this->mapper;
    }

    public function getUserIdentifierAttributeName(): string
    {
        return $this->attributeName ?? $this->userIdentifierField;
    }

    public function getJwtClaimName(): ?string
    {
        return $this->jwtClaimName;
    }

    public function shouldExposeInJwt(): bool
    {
        return $this->exposeInJwt;
    }

    public function shouldCreateIfMissing(): bool
    {
        return $this->createIfMissing;
    }

    public function shouldEnsureUserIdentifierAttribute(): bool
    {
        return $this->exposeInJwt || $this->createIfMissing;
    }

    public function buildEnsureUserIdentifierAttributeDto(): EnsureUserIdentifierAttributeDto
    {
        return new EnsureUserIdentifierAttributeDto(
            attributeName: $this->getUserIdentifierAttributeName(),
            displayName: $this->buildAttributeDisplayName(),
            createIfMissing: $this->createIfMissing,
            exposeInJwt: $this->exposeInJwt,
            jwtClaimName: $this->jwtClaimName,
        );
    }

    public function resolveUserIdentifierValue(object $localUser): string
    {
        if (!$localUser instanceof $this->className) {
            throw new LogicException(
                sprintf(
                    'Expected local user instance of "%s", got "%s".',
                    $this->className,
                    $localUser::class
                )
            );
        }

        $value = $this->resolveIdentifierProperty()->getValue($localUser);

        return match (true) {
            is_string($value) => $this->assertIdentifierStringValue(value: $value),
            is_int($value), is_float($value) => (string) $value,
            $value instanceof BackedEnum => is_string($value->value)
                ? $this->assertIdentifierStringValue(value: $value->value)
                : (string) $value->value,
            $value instanceof Stringable => $this->assertIdentifierStringValue(value: (string) $value),
            default => throw new LogicException(
                sprintf(
                    'Configured user identifier field "%s" on "%s" must resolve to a stringable scalar value.',
                    $this->userIdentifierField,
                    $this->className
                )
            ),
        };
    }

    private function resolveIdentifierProperty(): ReflectionProperty
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);

        do {
            if ($reflection->hasProperty($this->userIdentifierField)) {
                return $reflection->getProperty($this->userIdentifierField);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection instanceof ReflectionClass);

        throw new InvalidArgumentException(
            sprintf(
                'Configured user identifier field "%s" was not found on "%s".',
                $this->userIdentifierField,
                $this->className
            )
        );
    }

    private function assertIdentifierStringValue(string $value): string
    {
        if ($value === '') {
            throw new LogicException(
                sprintf(
                    'Configured user identifier field "%s" on "%s" cannot resolve to an empty string.',
                    $this->userIdentifierField,
                    $this->className
                )
            );
        }

        return $value;
    }

    private function buildAttributeDisplayName(): string
    {
        return ucwords(str_replace(['-', '_', '.'], ' ', $this->getUserIdentifierAttributeName()));
    }
}
