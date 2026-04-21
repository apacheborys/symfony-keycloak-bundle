<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Model;

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
}
