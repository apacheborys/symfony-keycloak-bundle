<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Service\Stub;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;

final class CapturingUserIdentifierAttributeService implements KeycloakUserIdentifierAttributeServiceInterface
{
    /**
     * @var list<array{realm: string, dto: EnsureUserIdentifierAttributeDto}>
     */
    public array $calls = [];

    public ?string $capturedRealm = null;

    public ?EnsureUserIdentifierAttributeDto $capturedDto = null;

    #[\Override]
    public function ensureUserIdentifierAttribute(
        string $realm,
        EnsureUserIdentifierAttributeDto $dto
    ): void {
        $this->capturedRealm = $realm;
        $this->capturedDto = $dto;
        $this->calls[] = [
            'realm' => $realm,
            'dto' => $dto,
        ];
    }
}
