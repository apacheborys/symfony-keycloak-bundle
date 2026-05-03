<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeRequiredDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityAttributeConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use LogicException;

final readonly class KeycloakBootstrapper
{
    /** @var array<class-string, UserEntityConfig> */
    private array $userEntityConfigs;

    /**
     * @param iterable<UserEntityConfig> $userEntityConfigs
     */
    public function __construct(
        private KeycloakUserIdentifierAttributeServiceInterface $userIdentifierAttributeService,
        private KeycloakHttpClientInterface $httpClient,
        iterable $userEntityConfigs,
    ) {
        /** @var array<class-string, UserEntityConfig> $resolvedConfigs */
        $resolvedConfigs = [];
        foreach ($userEntityConfigs as $userEntityConfig) {
            /** @var class-string $className */
            $className = $userEntityConfig->getClassName();
            $resolvedConfigs[$className] = $userEntityConfig;
        }

        $this->userEntityConfigs = $resolvedConfigs;
    }

    /**
     * @param class-string $userEntityClass
     */
    public function ensureUserIdentifierAttribute(string $userEntityClass): void
    {
        $userEntityConfig = $this->getUserEntityConfig(userEntityClass: $userEntityClass);
        $identifierAttributeConfig = $userEntityConfig->getUserIdentifierAttributeConfig();

        $this->ensureAttribute(
            realm: $userEntityConfig->getRealm(),
            attributeConfig: $identifierAttributeConfig,
            forceCreateIfMissing: true,
        );
    }

    /**
     * @param class-string $userEntityClass
     */
    public function ensureConfiguredAttributes(string $userEntityClass): void
    {
        $userEntityConfig = $this->getUserEntityConfig(userEntityClass: $userEntityClass);

        foreach ($userEntityConfig->getAttributeConfigs() as $attributeConfig) {
            if (!$attributeConfig->requiresBootstrapSync()) {
                continue;
            }

            $this->ensureAttribute(
                realm: $userEntityConfig->getRealm(),
                attributeConfig: $attributeConfig,
            );
        }
    }

    /**
     * @param class-string $userEntityClass
     */
    private function getUserEntityConfig(string $userEntityClass): UserEntityConfig
    {
        if (isset($this->userEntityConfigs[$userEntityClass])) {
            return $this->userEntityConfigs[$userEntityClass];
        }

        throw new LogicException(
            message: sprintf(
                'Keycloak bootstrapper does not have configuration for user entity "%s".',
                $userEntityClass,
            )
        );
    }

    private function ensureAttribute(
        string $realm,
        UserEntityAttributeConfig $attributeConfig,
        bool $forceCreateIfMissing = false,
    ): void {
        $this->userIdentifierAttributeService->ensureUserIdentifierAttribute(
            realm: $realm,
            dto: $attributeConfig->buildEnsureAttributeDto(forceCreateIfMissing: $forceCreateIfMissing),
        );

        if (!$attributeConfig->hasRequiredConfiguration()) {
            return;
        }

        $this->synchronizeRequiredConfiguration(
            realm: $realm,
            attributeConfig: $attributeConfig,
        );
    }

    private function synchronizeRequiredConfiguration(
        string $realm,
        UserEntityAttributeConfig $attributeConfig,
    ): void {
        $profile = $this->httpClient->getUserProfile(
            dto: new GetUserProfileDto(realm: $realm),
        );

        $currentAttribute = $this->findAttribute(
            profile: $profile,
            attributeName: $attributeConfig->getAttributeName(),
        );
        if ($currentAttribute === null) {
            throw new LogicException(
                sprintf(
                    'Keycloak user-profile attribute "%s" was not found in realm "%s" after bootstrap.',
                    $attributeConfig->getAttributeName(),
                    $realm,
                )
            );
        }

        if (
            $this->requiredRulesAreEqual(
                current: $currentAttribute->getRequired(),
                configured: $attributeConfig->getRequired(),
            )
        ) {
            return;
        }

        $this->httpClient->updateUserProfileAttribute(
            dto: new UpdateUserProfileAttributeDto(
                realm: $realm,
                attribute: new AttributeDto(
                    name: $currentAttribute->getName(),
                    displayName: $currentAttribute->getDisplayName(),
                    permissions: $currentAttribute->getPermissions(),
                    multivalued: $currentAttribute->isMultivalued(),
                    annotations: $currentAttribute->getAnnotations(),
                    required: $attributeConfig->getRequired(),
                    validators: $currentAttribute->getValidators(),
                    extra: $currentAttribute->getExtra(),
                ),
            ),
        );
    }

    private function findAttribute(UserProfileDto $profile, string $attributeName): ?AttributeDto
    {
        foreach ($profile->getAttributes() as $attribute) {
            if ($attribute->getName() === $attributeName) {
                return $attribute;
            }
        }

        return null;
    }

    private function requiredRulesAreEqual(
        ?AttributeRequiredDto $current,
        ?AttributeRequiredDto $configured,
    ): bool {
        if ($current === null || $configured === null) {
            return $current === $configured;
        }

        return $current->toArray() === $configured->toArray();
    }
}
