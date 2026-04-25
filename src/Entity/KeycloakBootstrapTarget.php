<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Entity;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use DateTimeImmutable;
use DateTimeInterface;
use Override;

final readonly class KeycloakBootstrapTarget implements KeycloakUserInterface
{
    public function __construct(
        private UserEntityConfig $userEntityConfig,
    ) {
    }

    public function getUserEntityConfig(): UserEntityConfig
    {
        return $this->userEntityConfig;
    }

    #[Override]
    public function getId(): string
    {
        return 'bootstrap:' . $this->userEntityConfig->getClassName();
    }

    #[Override]
    public function getUsername(): string
    {
        return '__keycloak_bootstrap__';
    }

    #[Override]
    public function getEmail(): string
    {
        return 'bootstrap@example.invalid';
    }

    #[Override]
    public function isEmailVerified(): bool
    {
        return false;
    }

    #[Override]
    public function getFirstName(): string
    {
        return 'Keycloak';
    }

    #[Override]
    public function getLastName(): string
    {
        return 'Bootstrap';
    }

    #[Override]
    public function getRoles(): array
    {
        return [];
    }

    #[Override]
    public function getCreatedAt(): DateTimeInterface
    {
        return new DateTimeImmutable();
    }

    #[Override]
    public function isEnabled(): bool
    {
        return true;
    }
}
