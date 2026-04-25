<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Model;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use InvalidArgumentException;

final readonly class UserEntityConfig
{
    /** @var class-string */
    private string $className;

    /** @var list<UserEntityAttributeConfig> */
    private array $attributeConfigs;

    /**
     * @param list<array{
     *  property: string,
     *  attribute_name: string|null,
     *  jwt_claim_name: string|null,
     *  create_if_missing: bool
     * }> $attributesMap
     */
    public function __construct(
        private string $realm,
        string $className,
        private string $userIdentifierField,
        private string $rolePrefix = '',
        private string $roleSuffix = '',
        private string $mapper = LocalEntityMapper::class,
        array $attributesMap = [],
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

        $this->assertUserIdentifierFieldIsValid();
        $this->attributeConfigs = $this->buildAttributeConfigs(attributesMap: $attributesMap);
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

    /**
     * @return list<UserEntityAttributeConfig>
     */
    public function getAttributeConfigs(): array
    {
        return $this->attributeConfigs;
    }

    public function getUserIdentifierAttributeConfig(): UserEntityAttributeConfig
    {
        foreach ($this->attributeConfigs as $attributeConfig) {
            if ($attributeConfig->getProperty() === $this->userIdentifierField) {
                return $attributeConfig;
            }
        }

        throw new InvalidArgumentException(
            sprintf(
                'User identifier field "%s" must be represented in attributes_map for "%s".',
                $this->userIdentifierField,
                $this->className,
            )
        );
    }

    /**
     * @return list<string>
     */
    public function getUserIdentifierJwtClaimNames(): array
    {
        return $this->getUserIdentifierAttributeConfig()->getJwtClaimSearchNames();
    }

    public function buildEnsureUserIdentifierAttributeDto(): EnsureUserIdentifierAttributeDto
    {
        return $this->getUserIdentifierAttributeConfig()->buildEnsureAttributeDto();
    }

    /**
     * @return array<string, string>
     */
    public function resolveMappedAttributes(object $localUser): array
    {
        $resolvedAttributes = [];
        foreach ($this->attributeConfigs as $attributeConfig) {
            $resolvedAttributes[$attributeConfig->getAttributeName()] = $attributeConfig->resolveValue($localUser);
        }

        return $resolvedAttributes;
    }

    /**
     * @param list<array{
     *  property: string,
     *  attribute_name: string|null,
     *  jwt_claim_name: string|null,
     *  create_if_missing: bool
     * }> $attributesMap
     * @return list<UserEntityAttributeConfig>
     */
    private function buildAttributeConfigs(array $attributesMap): array
    {
        $normalizedAttributesMap = $this->normalizeAttributeMap(attributesMap: $attributesMap);

        $attributeConfigs = [];
        $seenProperties = [];
        $seenAttributeNames = [];
        foreach ($normalizedAttributesMap as $attributeConfig) {
            $resolvedAttributeConfig = new UserEntityAttributeConfig(
                className: $this->className,
                property: $attributeConfig['property'],
                attributeName: $attributeConfig['attribute_name'],
                jwtClaimName: $attributeConfig['jwt_claim_name'],
                createIfMissing: $attributeConfig['create_if_missing'],
            );

            $property = $resolvedAttributeConfig->getProperty();
            if (isset($seenProperties[$property])) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configured attribute property "%s" is duplicated for "%s".',
                        $property,
                        $this->className,
                    )
                );
            }
            $seenProperties[$property] = true;

            $attributeName = $resolvedAttributeConfig->getAttributeName();
            if (isset($seenAttributeNames[$attributeName])) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configured Keycloak attribute name "%s" is duplicated for "%s".',
                        $attributeName,
                        $this->className,
                    )
                );
            }
            $seenAttributeNames[$attributeName] = true;

            $attributeConfigs[] = $resolvedAttributeConfig;
        }

        return $attributeConfigs;
    }

    /**
     * @param list<array{
     *  property: string,
     *  attribute_name: string|null,
     *  jwt_claim_name: string|null,
     *  create_if_missing: bool
     * }> $attributesMap
     * @return list<array{
     *  property: string,
     *  attribute_name: string|null,
     *  jwt_claim_name: string|null,
     *  create_if_missing: bool
     * }>
     */
    private function normalizeAttributeMap(array $attributesMap): array
    {
        foreach ($attributesMap as $index => $attributeConfig) {
            if ($attributeConfig['property'] !== $this->userIdentifierField) {
                continue;
            }

            $attributesMap[$index]['jwt_claim_name'] ??= $this->buildDefaultJwtClaimName(
                attributeName: $attributeConfig['attribute_name'] ?? $attributeConfig['property']
            );

            return $attributesMap;
        }

        array_unshift(
            $attributesMap,
            [
                'property' => $this->userIdentifierField,
                'attribute_name' => null,
                'jwt_claim_name' => $this->buildDefaultJwtClaimName(attributeName: $this->userIdentifierField),
                'create_if_missing' => false,
            ],
        );

        return $attributesMap;
    }

    private function assertUserIdentifierFieldIsValid(): void
    {
        try {
            new UserEntityAttributeConfig(
                className: $this->className,
                property: $this->userIdentifierField,
            );
        } catch (InvalidArgumentException $exception) {
            $message = str_replace(
                ['Configured attribute property', 'attribute property'],
                ['Configured user identifier field', 'user identifier field'],
                $exception->getMessage(),
            );

            throw new InvalidArgumentException($message, previous: $exception);
        }
    }

    private function buildDefaultJwtClaimName(string $attributeName): string
    {
        return str_replace('-', '_', $attributeName);
    }
}
