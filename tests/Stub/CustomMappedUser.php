<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Override;

final readonly class CustomMappedUser implements KeycloakUserInterface
{
    public function __construct(
        private string $id = 'f8c44ba7-4f23-4974-abf1-9f4685c8f5ad',
        private string $keycloakId = 'f97d59cc-eb9d-4370-91b1-8f7bfbb86c20',
        private string $username = 'custom-user',
        private string $email = 'custom@example.test',
        private bool $emailVerified = true,
        private string $firstName = 'Custom',
        private string $lastName = 'User',
        private bool $enabled = true,
        private string $externalIdentifier = 'external-custom-user-reference-f8c44ba7-4f23-4974-abf1-9f4685c8f5ad',
        /** @var string[] */
        private array $roles = ['ROLE_CUSTOM'],
    ) {
    }

    #[Override]
    public function getKeycloakId(): string
    {
        return $this->keycloakId;
    }

    #[Override]
    public function getUsername(): string
    {
        return $this->username;
    }

    #[Override]
    public function getEmail(): string
    {
        return $this->email;
    }

    #[Override]
    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    #[Override]
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    #[Override]
    public function getLastName(): string
    {
        return $this->lastName;
    }

    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getExternalIdentifier(): string
    {
        return $this->externalIdentifier;
    }

    public function getId(): string
    {
        return $this->id;
    }

    #[Override]
    public function getCreatedAt(): DateTimeInterface
    {
        return new DateTimeImmutable();
    }

    #[Override]
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
