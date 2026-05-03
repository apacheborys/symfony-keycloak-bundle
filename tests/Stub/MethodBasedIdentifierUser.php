<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Override;

final readonly class MethodBasedIdentifierUser implements KeycloakUserInterface
{
    public function __construct(
        private string $localIdentifier = 'method-based-local-user-id',
        private ?string $keycloakId = null,
        private string $username = 'method-user',
        private string $email = 'method@example.test',
        private bool $emailVerified = true,
        private string $firstName = 'Method',
        private string $lastName = 'User',
        private bool $enabled = true,
        /** @var string[] */
        private array $roles = [],
    ) {
    }

    #[Override]
    public function getId(): string
    {
        return $this->localIdentifier;
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
