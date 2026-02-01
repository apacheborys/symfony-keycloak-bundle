<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Kernel;

use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\SymfonyKeycloakBridgeBundle\KeycloakBridgeBundle;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use Nyholm\Psr7\Factory\Psr17Factory;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Kernel\Stub\NullPsr18Client;
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
                    $mapperId = LocalEntityMapper::class;

                    if ($container->hasDefinition($serviceId)) {
                        $container->getDefinition($serviceId)->setPublic(true);
                    }

                    if ($container->hasAlias($aliasId)) {
                        $container->getAlias($aliasId)->setPublic(true);
                    }

                    if ($container->hasDefinition($mapperId)) {
                        $container->getDefinition($mapperId)->setPublic(true);
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
                    ],
                ],
            ]
        );
    }

    protected function configureRoutes(LoaderInterface $loader): void
    {
    }
}
