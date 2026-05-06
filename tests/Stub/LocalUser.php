<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Override;

final readonly class LocalUser implements KeycloakUserInterface
{
    public function __construct(
        private string $id = '58f5b67f-bcf4-4d12-86a3-a54f7704f326',
        private ?string $keycloakId = '5d9f44d8-a86e-4028-a237-8fe2e5ecdb44',
        private string $username = 'local-username',
        private string $email = 'local@example.test',
        private bool $emailVerified = true,
        private string $firstName = 'Local',
        private string $lastName = 'User',
        private bool $enabled = true,
        private string $localIdentifier = 'local-user-reference-58f5b67f-bcf4-4d12-86a3-a54f7704f326',
        /** @var string[] */
        private array $roles = [],
    ) {
    }

    #[Override]
    public function getKeycloakId(): ?string
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

    public function getLocalIdentifier(): string
    {
        return $this->localIdentifier;
    }

    #[\Override]
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
