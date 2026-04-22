<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Integration;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakJwtVerificationServiceInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtAuthenticator;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\ConfiguredKeycloakService;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Kernel\TestKernel;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\CustomMappedUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Mapper\CustomMappedUserMapper;
use Override;
use Ramsey\Uuid\Uuid;
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

        self::assertInstanceOf(ConfiguredKeycloakService::class, $service);
        self::assertInstanceOf(KeycloakService::class, $container->get(KeycloakService::class));
        self::assertNotSame($container->get(KeycloakService::class), $service);

        self::assertTrue($container->has(KeycloakServiceInterface::class));
        self::assertTrue($container->has(KeycloakHttpClientInterface::class));
        self::assertTrue($container->has(KeycloakJwtVerificationServiceInterface::class));
        self::assertTrue($container->has(KeycloakUserIdentifierAttributeServiceInterface::class));
        self::assertTrue($container->has(KeycloakJwtAuthenticator::class));
        self::assertInstanceOf(KeycloakJwtAuthenticator::class, $container->get(KeycloakJwtAuthenticator::class));
    }

    public function testUserEntityRealmMapping(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        /**
         * @var LocalEntityMapper $mapper
         */
        $mapper = $container->get(LocalEntityMapper::class);

        $user = new LocalUser(roles: ['ROLE_USER', 'ROLE_ADMIN']);
        self::assertTrue($mapper->support($user));

        $dto = $mapper->prepareLocalUserForKeycloakUserCreation(
            $user,
            [
                new RoleDto(
                    name: 'payment.ROLE_USER.svc',
                    id: Uuid::fromString('a7d9fd61-1f20-4d69-9f8f-af72784b9a02')
                ),
                new RoleDto(
                    name: 'payment.ROLE_ADMIN.svc',
                    id: Uuid::fromString('7ae8eba6-f101-45a5-9f9e-a77032410cc5')
                ),
            ]
        );
        self::assertSame('users-realm', $dto->getRealm());
        self::assertCount(2, $dto->getRoles());
        self::assertSame('payment.ROLE_USER.svc', $dto->getRoles()[0]->getName());
        self::assertSame('payment.ROLE_ADMIN.svc', $dto->getRoles()[1]->getName());
        self::assertSame(
            ['local-user-id' => ['local-user-reference-58f5b67f-bcf4-4d12-86a3-a54f7704f326']],
            $dto->getAttributes()
        );
    }

    public function testCustomMapperConfigurationOverridesDefaultMapperSupport(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        /**
         * @var LocalEntityMapper $localEntityMapper
         */
        $localEntityMapper = $container->get(LocalEntityMapper::class);
        /**
         * @var CustomMappedUserMapper $customMapper
         */
        $customMapper = $container->get(CustomMappedUserMapper::class);

        $customUser = new CustomMappedUser();
        $defaultUser = new LocalUser();

        self::assertFalse($localEntityMapper->support($customUser));
        self::assertTrue($customMapper->support($customUser));
        self::assertTrue($localEntityMapper->support($defaultUser));
    }

    public function testUserEntityConfigResolvesConfiguredIdentifierField(): void
    {
        $userEntityConfig = new UserEntityConfig(
            realm: 'users-realm',
            className: LocalUser::class,
            userIdentifierField: 'localIdentifier',
            attributeName: 'local-user-id',
            jwtClaimName: 'local_user_id',
            exposeInJwt: true,
            createIfMissing: true,
        );

        self::assertSame('localIdentifier', $userEntityConfig->getUserIdentifierField());
        self::assertSame('local-user-id', $userEntityConfig->getUserIdentifierAttributeName());
        self::assertSame('local_user_id', $userEntityConfig->getJwtClaimName());
        self::assertTrue($userEntityConfig->shouldExposeInJwt());
        self::assertTrue($userEntityConfig->shouldCreateIfMissing());
        self::assertTrue($userEntityConfig->shouldEnsureUserIdentifierAttribute());
        self::assertSame(
            'local-user-id',
            $userEntityConfig->buildEnsureUserIdentifierAttributeDto()->getAttributeName()
        );
        self::assertSame(
            'local-user-reference-58f5b67f-bcf4-4d12-86a3-a54f7704f326',
            $userEntityConfig->resolveUserIdentifierValue(new LocalUser())
        );
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
        self::assertSame($user->getId(), $dto->getUserId()->toString());
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

    public function testUserEntityUpdateDiffMapping(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        /**
         * @var LocalEntityMapper $mapper
         */
        $mapper = $container->get(LocalEntityMapper::class);

        $oldUser = new LocalUser(
            email: 'before@example.test',
            firstName: 'Before',
            roles: ['ROLE_USER'],
        );
        $newUser = new LocalUser(
            email: 'after@example.test',
            firstName: 'After',
            localIdentifier: 'updated-local-user-reference',
            roles: ['ROLE_USER', 'ROLE_ADMIN'],
        );

        $dto = $mapper->prepareLocalUserDiffForKeycloakUserUpdate(
            oldUserVersion: $oldUser,
            newUserVersion: $newUser,
            availableRoles: [
                new RoleDto(
                    name: 'payment.ROLE_USER.svc',
                    id: Uuid::fromString('ebec7392-12ea-4d6a-b55a-6d17644f17c2')
                ),
                new RoleDto(
                    name: 'payment.ROLE_ADMIN.svc',
                    id: Uuid::fromString('2af3f4de-0251-4be3-9f33-ce4f3ce69a01')
                ),
            ],
        );

        self::assertSame('users-realm', $dto->getRealm());
        self::assertSame($newUser->getId(), $dto->getUserId()->toString());
        self::assertSame('after@example.test', $dto->getProfile()->getEmail());
        self::assertNotNull($dto->getProfile()->getRoles());
        self::assertCount(2, $dto->getProfile()->getRoles());
        self::assertSame('payment.ROLE_USER.svc', $dto->getProfile()->getRoles()[0]->getName());
        self::assertSame('payment.ROLE_ADMIN.svc', $dto->getProfile()->getRoles()[1]->getName());
        self::assertSame(
            ['local-user-id' => ['updated-local-user-reference']],
            $dto->getProfile()->getAttributes()
        );
    }
}
