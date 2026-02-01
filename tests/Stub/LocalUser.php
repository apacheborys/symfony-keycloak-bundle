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
        private string $id = 'local-user-id',
        private string $username = 'local-username',
        private string $email = 'local@example.test',
        private bool $emailVerified = true,
        private string $firstName = 'Local',
        private string $lastName = 'User',
        private bool $enabled = true,
    ) {
    }

    #[Override]
    public function getId(): string
    {
        return $this->id;
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
        return $this->enabled;
    }
}
