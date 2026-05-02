<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Model;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use InvalidArgumentException;

final readonly class UserEntityConfig
{
    /** @var class-string */
    private string $className;

    private UserEntityAttributeConfig $userIdentifierAttributeConfig;

    /** @var list<UserEntityAttributeConfig> */
    private array $attributeConfigs;

    /**
     * @param list<array{
     *  property: string,
     *  attribute_name: string|null,
     *  jwt_claim_name: string|null,
     *  create_if_missing: bool,
     *  required?: array{roles?: list<string>, scopes?: list<string>}|bool|null
     * }> $attributesMap
     */
    public function __construct(
        private string $realm,
        string $className,
        private bool $roleAllowCreation = false,
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

        $resolvedAttributeConfigs = $this->buildAttributeConfigs(attributesMap: $attributesMap);
        $this->userIdentifierAttributeConfig = $resolvedAttributeConfigs['identifier'];
        $this->attributeConfigs = $resolvedAttributeConfigs['attributes'];
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getRolePrefix(): string
    {
        return $this->rolePrefix;
    }

    public function isRoleCreationAllowed(): bool
    {
        return $this->roleAllowCreation;
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
        return $this->userIdentifierAttributeConfig;
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

    public function buildBootstrapUserIdentifierAttributeDto(): EnsureUserIdentifierAttributeDto
    {
        return $this->getUserIdentifierAttributeConfig()->buildEnsureAttributeDto(forceCreateIfMissing: true);
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
     *  create_if_missing: bool,
     *  required?: array{roles?: list<string>, scopes?: list<string>}|bool|null
     * }> $attributesMap
     * @return array{identifier: UserEntityAttributeConfig, attributes: list<UserEntityAttributeConfig>}
     */
    private function buildAttributeConfigs(array $attributesMap): array
    {
        $normalizedAttributesMap = $this->normalizeAttributeMap(attributesMap: $attributesMap);

        $attributeConfigs = [];
        $identifierAttributeConfig = null;
        $seenProperties = [];
        $seenAttributeNames = [];
        foreach ($normalizedAttributesMap as $attributeConfig) {
            $resolvedAttributeConfig = new UserEntityAttributeConfig(
                className: $this->className,
                property: $attributeConfig['property'],
                attributeName: $attributeConfig['attribute_name'],
                jwtClaimName: $attributeConfig['jwt_claim_name'],
                createIfMissing: $attributeConfig['create_if_missing'],
                required: $attributeConfig['required'] ?? null,
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

            if ($resolvedAttributeConfig->isLocalUserIdProperty()) {
                $identifierAttributeConfig = $resolvedAttributeConfig;
            }

            $attributeConfigs[] = $resolvedAttributeConfig;
        }

        if (!$identifierAttributeConfig instanceof UserEntityAttributeConfig) {
            throw new InvalidArgumentException(
                sprintf(
                    'Configured identifier mapping for "%s" must use attribute property "%s".',
                    $this->className,
                    UserEntityAttributeConfig::LOCAL_USER_ID_PROPERTY,
                )
            );
        }

        return [
            'identifier' => $identifierAttributeConfig,
            'attributes' => $attributeConfigs,
        ];
    }

    /**
     * @param list<array{
     *  property: string,
     *  attribute_name: string|null,
     *  jwt_claim_name: string|null,
     *  create_if_missing: bool,
     *  required?: array{roles?: list<string>, scopes?: list<string>}|bool|null
     * }> $attributesMap
     * @return list<array{
     *  property: string,
     *  attribute_name: string|null,
     *  jwt_claim_name: string|null,
     *  create_if_missing: bool,
     *  required?: array{roles?: list<string>, scopes?: list<string>}|bool|null
     * }>
     */
    private function normalizeAttributeMap(array $attributesMap): array
    {
        foreach ($attributesMap as $index => $attributeConfig) {
            if ($attributeConfig['property'] !== UserEntityAttributeConfig::LOCAL_USER_ID_PROPERTY) {
                continue;
            }

            $attributesMap[$index]['attribute_name'] ??= $this->getDefaultUserIdentifierAttributeName();
            $attributesMap[$index]['jwt_claim_name'] ??= $this->buildDefaultJwtClaimName(
                attributeName: $attributesMap[$index]['attribute_name']
            );

            return $attributesMap;
        }

        array_unshift(
            $attributesMap,
            [
                'property' => UserEntityAttributeConfig::LOCAL_USER_ID_PROPERTY,
                'attribute_name' => $this->getDefaultUserIdentifierAttributeName(),
                'jwt_claim_name' => $this->buildDefaultJwtClaimName(
                    attributeName: $this->getDefaultUserIdentifierAttributeName()
                ),
                'create_if_missing' => false,
                'required' => null,
            ],
        );

        return $attributesMap;
    }

    private function buildDefaultJwtClaimName(string $attributeName): string
    {
        return str_replace('-', '_', $attributeName);
    }

    private function getDefaultUserIdentifierAttributeName(): string
    {
        return LocalKeycloakUserBridgeMapperInterface::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME;
    }
}
