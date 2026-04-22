<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Override;

final readonly class ConfiguredKeycloakService implements KeycloakServiceInterface
{
    /** @var list<UserEntityConfig> */
    private array $userEntityConfigs;

    /**
     * @param iterable<UserEntityConfig> $userEntityConfigs
     */
    public function __construct(
        private KeycloakServiceInterface $inner,
        iterable $userEntityConfigs,
    ) {
        $configs = [];
        foreach ($userEntityConfigs as $userEntityConfig) {
            $configs[] = $userEntityConfig;
        }

        $this->userEntityConfigs = $configs;
    }

    #[Override]
    public function createUser(KeycloakUserInterface $localUser, PasswordDto $passwordDto): KeycloakUser
    {
        $this->ensureConfiguredUserIdentifierAttribute(localUser: $localUser);

        return $this->inner->createUser(localUser: $localUser, passwordDto: $passwordDto);
    }

    #[Override]
    public function updateUser(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): KeycloakUser {
        $this->ensureConfiguredUserIdentifierAttribute(localUser: $newUserVersion);

        return $this->inner->updateUser(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );
    }

    #[Override]
    public function deleteUser(KeycloakUserInterface $user): void
    {
        $this->inner->deleteUser(user: $user);
    }

    #[Override]
    public function ensureUserIdentifierAttribute(
        KeycloakUserInterface $localUser,
        EnsureUserIdentifierAttributeDto $dto
    ): void {
        $this->inner->ensureUserIdentifierAttribute(localUser: $localUser, dto: $dto);
    }

    /**
     * @return list<KeycloakRealm>
     */
    #[Override]
    public function getAvailableRealms(): array
    {
        return $this->inner->getAvailableRealms();
    }

    #[Override]
    public function verifyJwt(string $jwt): bool
    {
        return $this->inner->verifyJwt(jwt: $jwt);
    }

    #[Override]
    public function loginUser(KeycloakUserInterface $user, string $plainPassword): OidcTokenResponseDto
    {
        $this->ensureConfiguredUserIdentifierAttribute(localUser: $user);

        return $this->inner->loginUser(user: $user, plainPassword: $plainPassword);
    }

    #[Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->inner->refreshToken(dto: $dto);
    }

    private function ensureConfiguredUserIdentifierAttribute(KeycloakUserInterface $localUser): void
    {
        $userConfig = $this->findUserConfig(localUser: $localUser);
        if (!$userConfig instanceof UserEntityConfig || !$userConfig->shouldEnsureUserIdentifierAttribute()) {
            return;
        }

        $this->inner->ensureUserIdentifierAttribute(
            localUser: $localUser,
            dto: $userConfig->buildEnsureUserIdentifierAttributeDto(),
        );
    }

    private function findUserConfig(KeycloakUserInterface $localUser): ?UserEntityConfig
    {
        foreach ($this->userEntityConfigs as $userEntityConfig) {
            $configuredClass = $userEntityConfig->getClassName();
            if ($localUser instanceof $configuredClass) {
                return $userEntityConfig;
            }
        }

        return null;
    }
}
