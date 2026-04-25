<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Service\Stub;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;

final class CapturingUserIdentifierAttributeService implements KeycloakUserIdentifierAttributeServiceInterface
{
    public ?KeycloakUserInterface $capturedUser = null;

    public ?EnsureUserIdentifierAttributeDto $capturedDto = null;

    #[\Override]
    public function ensureUserIdentifierAttribute(
        KeycloakUserInterface $localUser,
        EnsureUserIdentifierAttributeDto $dto
    ): void {
        $this->capturedUser = $localUser;
        $this->capturedDto = $dto;
    }
}
