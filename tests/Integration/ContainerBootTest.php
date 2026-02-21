<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Integration;

use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Kernel\TestKernel;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

final class ContainerBootTest extends KernelTestCase
{
    /**
     * @var (callable(Throwable): void)|null
     */
    private mixed $previousExceptionHandler = null;

    #[Override]
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

    #[Override]
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
    #[Override]
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

    public function testUserEntityRealmMapping(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        /**
         * @var LocalEntityMapper $mapper
         */
        $mapper = $container->get(LocalEntityMapper::class);

        $user = new LocalUser();
        self::assertTrue($mapper->support($user));

        $dto = $mapper->prepareLocalUserForKeycloakUserCreation($user);
        self::assertSame('users-realm', $dto->getRealm());
    }

    public function testUserEntityDeletionMapping(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        /**
         * @var LocalEntityMapper $mapper
         */
        $mapper = $container->get(LocalEntityMapper::class);

        $user = new LocalUser();
        $dto = $mapper->prepareLocalUserForKeycloakUserDeletion($user);

        self::assertSame('users-realm', $dto->getRealm());
        self::assertSame($user->getId(), $dto->getUserId());
    }

    public function testUserEntityLoginMapping(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        /**
         * @var LocalEntityMapper $mapper
         */
        $mapper = $container->get(LocalEntityMapper::class);
        $user = new LocalUser();

        $dto = $mapper->prepareLocalUserForKeycloakLoginUser($user, 'secret-password');
        $formParams = $dto->toFormParams();

        self::assertSame('password', $formParams['grant_type']);
        self::assertSame('bridge-client', $formParams['client_id']);
        self::assertSame('bridge-secret', $formParams['client_secret']);
        self::assertSame($user->getUsername(), $formParams['username']);
        self::assertSame('secret-password', $formParams['password']);
    }
}
