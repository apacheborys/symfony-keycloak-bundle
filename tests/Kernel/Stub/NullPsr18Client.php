<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Kernel\Stub;

use Nyholm\Psr7\Response;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class NullPsr18Client implements ClientInterface
{
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response(200);
    }
}
