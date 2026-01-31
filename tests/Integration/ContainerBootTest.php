<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Integration;

use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

final class ContainerBootTest extends KernelTestCase
{
    /**
     * @var (callable(Throwable): void)|null
     */
    private mixed $previousExceptionHandler = null;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(
            static function (Throwable $exception): void {
                throw $exception;
            }
        );
        restore_exception_handler();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        } else {
            restore_exception_handler();
        }

        parent::tearDown();
    }

    /**
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): TestKernel
    {
        return new TestKernel('test', true);
    }

    public function testContainerProvidesKeycloakService(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $service = $container->get(KeycloakServiceInterface::class);

        self::assertInstanceOf(KeycloakService::class, $service);

        self::assertTrue($container->has(KeycloakServiceInterface::class));
        self::assertTrue($container->has(KeycloakHttpClientInterface::class));
    }
}
