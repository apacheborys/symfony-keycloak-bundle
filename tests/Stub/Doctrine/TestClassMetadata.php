<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Doctrine;

use Doctrine\Persistence\Mapping\ClassMetadata;
use ReflectionClass;

/**
 * @implements ClassMetadata<object>
 */
final readonly class TestClassMetadata implements ClassMetadata
{
    /**
     * @param class-string $className
     * @param list<string> $identifierFields
     * @param list<string> $fieldNames
     * @param list<string> $associationNames
     */
    public function __construct(
        private string $className,
        private array $identifierFields,
        private array $fieldNames,
        private array $associationNames = [],
    ) {
    }

    #[\Override]
    public function getName(): string
    {
        return $this->className;
    }

    #[\Override]
    public function getIdentifier(): array
    {
        return $this->identifierFields;
    }

    #[\Override]
    public function getReflectionClass(): ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    #[\Override]
    public function isIdentifier(string $fieldName): bool
    {
        return in_array($fieldName, $this->identifierFields, true);
    }

    #[\Override]
    public function hasField(string $fieldName): bool
    {
        return in_array($fieldName, $this->fieldNames, true);
    }

    #[\Override]
    public function hasAssociation(string $fieldName): bool
    {
        return in_array($fieldName, $this->associationNames, true);
    }

    #[\Override]
    public function isSingleValuedAssociation(string $fieldName): bool
    {
        return $this->hasAssociation($fieldName);
    }

    #[\Override]
    public function isCollectionValuedAssociation(string $fieldName): bool
    {
        return false;
    }

    #[\Override]
    public function getFieldNames(): array
    {
        return $this->fieldNames;
    }

    #[\Override]
    public function getIdentifierFieldNames(): array
    {
        return $this->identifierFields;
    }

    #[\Override]
    public function getAssociationNames(): array
    {
        return $this->associationNames;
    }

    #[\Override]
    public function getTypeOfField(string $fieldName): ?string
    {
        return $this->hasField($fieldName) ? 'string' : null;
    }

    #[\Override]
    public function getAssociationTargetClass(string $assocName): ?string
    {
        return null;
    }

    #[\Override]
    public function isAssociationInverseSide(string $assocName): bool
    {
        return false;
    }

    #[\Override]
    public function getAssociationMappedByTargetField(string $assocName): string
    {
        return '';
    }

    #[\Override]
    public function getIdentifierValues(object $object): array
    {
        $values = [];
        $reflection = $this->getReflectionClass();

        foreach ($this->identifierFields as $identifierField) {
            if (!$reflection->hasProperty($identifierField)) {
                continue;
            }

            $values[$identifierField] = $reflection->getProperty($identifierField)->getValue($object);
        }

        return $values;
    }
}
