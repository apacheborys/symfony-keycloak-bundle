<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Security;

use Override;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class KeycloakJwtUser implements UserInterface
{
    /** @var non-empty-string */
    private string $userIdentifier;

    /** @var list<string> */
    private array $roles;

    /**
     * @param list<string> $roles
     * @param non-empty-string $userIdentifier
     */
    public function __construct(string $userIdentifier, array $roles, private string $rawToken)
    {
        $normalizedUserIdentifier = trim($userIdentifier);
        if ($normalizedUserIdentifier === '') {
            throw new \InvalidArgumentException('User identifier cannot be empty.');
        }

        $this->userIdentifier = $normalizedUserIdentifier;
        $this->roles = $this->normalizeRoles(roles: $roles);
    }

    public function getRawToken(): string
    {
        return $this->rawToken;
    }

    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[Override]
    public function eraseCredentials(): void
    {
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $trimmedRole = trim($role);
            if ($trimmedRole === '') {
                continue;
            }

            $normalized[$trimmedRole] = true;
        }

        if ($normalized === []) {
            $normalized['ROLE_USER'] = true;
        }

        return array_keys($normalized);
    }
}
