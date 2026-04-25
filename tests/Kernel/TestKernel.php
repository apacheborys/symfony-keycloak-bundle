<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Kernel;

use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakJwtVerificationServiceInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\KeycloakBridgeBundle;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtAuthenticator;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\KeycloakBootstrapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Doctrine\TestManagerRegistry;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\CustomMappedUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Mapper\CustomMappedUserMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Kernel\Stub\NullPsr18Client;
use Doctrine\Persistence\ManagerRegistry;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new KeycloakBridgeBundle(),
        ];
    }

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(
            new class implements CompilerPassInterface {
                public function process(ContainerBuilder $container): void
                {
                    $serviceId = KeycloakService::class;
                    $aliasId = KeycloakServiceInterface::class;
                    $jwtAliasId = KeycloakJwtVerificationServiceInterface::class;
                    $identifierAliasId = KeycloakUserIdentifierAttributeServiceInterface::class;
                    $mapperId = LocalEntityMapper::class;
                    $customMapperId = CustomMappedUserMapper::class;
                    $authenticatorId = KeycloakJwtAuthenticator::class;
                    $authenticatorAliasId = 'keycloak.jwt_authenticator';
                    $bootstrapperId = KeycloakBootstrapper::class;
                    $bootstrapperAliasId = 'keycloak.bootstrapper';

                    if ($container->hasDefinition($serviceId)) {
                        $container->getDefinition($serviceId)->setPublic(true);
                    }

                    if ($container->hasAlias($aliasId)) {
                        $container->getAlias($aliasId)->setPublic(true);
                    }

                    if ($container->hasAlias($jwtAliasId)) {
                        $container->getAlias($jwtAliasId)->setPublic(true);
                    }

                    if ($container->hasAlias($identifierAliasId)) {
                        $container->getAlias($identifierAliasId)->setPublic(true);
                    }

                    if ($container->hasDefinition($mapperId)) {
                        $container->getDefinition($mapperId)->setPublic(true);
                    }

                    if ($container->hasDefinition($customMapperId)) {
                        $container->getDefinition($customMapperId)->setPublic(true);
                    }

                    if ($container->hasDefinition($authenticatorId)) {
                        $container->getDefinition($authenticatorId)->setPublic(true);
                    }

                    if ($container->hasAlias($authenticatorAliasId)) {
                        $container->getAlias($authenticatorAliasId)->setPublic(true);
                    }

                    if ($container->hasDefinition($bootstrapperId)) {
                        $container->getDefinition($bootstrapperId)->setPublic(true);
                    }

                    if ($container->hasAlias($bootstrapperAliasId)) {
                        $container->getAlias($bootstrapperAliasId)->setPublic(true);
                    }
                }
            }
        );
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension(
            namespace: 'framework',
            config: [
                'secret' => 'test',
                'test' => true,
            ]
        );

        $services = $container->services();

        $services
            ->set('psr18.client', NullPsr18Client::class);

        $services
            ->set('psr17.factory', Psr17Factory::class);

        $services
            ->set('doctrine', TestManagerRegistry::class)
            ->args(arguments: [[
                LocalUser::class => 'id',
                CustomMappedUser::class => 'id',
            ]]);

        $services->alias(ManagerRegistry::class, 'doctrine');

        $container->extension(
            namespace: 'keycloak_bridge',
            config: [
                'base_url' => 'https://example.test',
                'client_realm' => 'master',
                'client_id' => 'bridge-client',
                'client_secret' => 'bridge-secret',
                'http_client_service' => 'psr18.client',
                'request_factory_service' => 'psr17.factory',
                'stream_factory_service' => 'psr17.factory',
                'realm_list_ttl' => 30,
                'user_entities' => [
                    LocalUser::class => [
                        'realm' => 'users-realm',
                        'attributes_map' => [
                            [
                                'property' => 'id',
                                'attribute_name' => 'local-user-id',
                                'create_if_missing' => true,
                            ],
                            [
                                'property' => 'firstName',
                                'attribute_name' => 'profile-first-name',
                                'create_if_missing' => true,
                            ],
                        ],
                        'role_prefix' => 'payment.',
                        'role_suffix' => '.svc',
                    ],
                    CustomMappedUser::class => [
                        'realm' => 'custom-realm',
                        'mapper' => CustomMappedUserMapper::class,
                    ],
                ],
            ]
        );
    }

    protected function configureRoutes(LoaderInterface $loader): void
    {
    }
}
