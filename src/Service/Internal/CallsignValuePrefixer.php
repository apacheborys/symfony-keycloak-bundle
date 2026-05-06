<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Service\Internal;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\AttributeValueDto;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;

final readonly class CallsignValuePrefixer
{
    private string $callsignPrefix;

    public function __construct(string $callsign)
    {
        $normalizedCallsign = rtrim(trim($callsign), '.');
        if ($normalizedCallsign === '') {
            throw new InvalidArgumentException('The "callsign" configuration value cannot be blank.');
        }

        $this->callsignPrefix = $normalizedCallsign . '.';
    }

    public function prefix(string $value): string
    {
        if (str_starts_with($value, $this->callsignPrefix)) {
            return $value;
        }

        return $this->callsignPrefix . $value;
    }

    public function strip(string $value): string
    {
        if (!str_starts_with($value, $this->callsignPrefix)) {
            return $value;
        }

        return substr($value, strlen($this->callsignPrefix));
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, string>
     */
    public function prefixAttributeMap(array $attributes): array
    {
        $prefixedAttributes = [];
        foreach ($attributes as $attributeName => $attributeValue) {
            $prefixedAttributes[$attributeName] = $this->prefix($attributeValue);
        }

        return $prefixedAttributes;
    }

    public function prefixAttribute(AttributeValueDto $attribute): AttributeValueDto
    {
        return new AttributeValueDto(
            attributeName: $attribute->getAttributeName(),
            attributeValue: $this->prefixAttributeValue($attribute->getAttributeValue()),
        );
    }

    /**
     * @param list<AttributeValueDto> $attributes
     * @return list<AttributeValueDto>
     */
    public function prefixAttributes(array $attributes): array
    {
        return array_map(
            fn (AttributeValueDto $attribute): AttributeValueDto => $this->prefixAttribute($attribute),
            $attributes,
        );
    }

    /**
     * @param ?list<RoleDto> $roles
     * @return ?list<RoleDto>
     */
    public function prefixRoles(?array $roles): ?array
    {
        if ($roles === null) {
            return null;
        }

        return array_map(
            fn (RoleDto $role): RoleDto => $this->mapRoleName(
                role: $role,
                transformer: fn (string $roleName): string => $this->prefix($roleName),
            ),
            $roles,
        );
    }

    /**
     * @param list<RoleDto> $roles
     * @return list<RoleDto>
     */
    public function stripRoles(array $roles): array
    {
        return array_map(
            fn (RoleDto $role): RoleDto => $this->mapRoleName(
                role: $role,
                transformer: fn (string $roleName): string => $this->strip($roleName),
            ),
            $roles,
        );
    }

    /**
     * @param int|string|UuidInterface|list<string> $attributeValue
     * @return string|list<string>
     */
    private function prefixAttributeValue(int|string|UuidInterface|array $attributeValue): string|array
    {
        if (is_array($attributeValue)) {
            return array_map(
                fn (string $value): string => $this->prefix($value),
                $attributeValue,
            );
        }

        if ($attributeValue instanceof UuidInterface) {
            return $this->prefix($attributeValue->toString());
        }

        return $this->prefix((string) $attributeValue);
    }

    private function mapRoleName(RoleDto $role, callable $transformer): RoleDto
    {
        return new RoleDto(
            name: $transformer($role->getName()),
            id: $role->getId(),
            description: $role->getDescription(),
            composite: $role->isComposite(),
            clientRole: $role->isClientRole(),
            containerId: $role->getContainerId(),
        );
    }
}
