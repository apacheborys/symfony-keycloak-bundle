<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class KeycloakBridgeBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition
            ->rootNode()
            ->children()
                ->scalarNode('base_url')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('http_client_service')->defaultNull()->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $httpClientRef = $config['http_client_service'] !== null
            ? service($config['http_client_service'])
            : null;

        $services
            ->set(KeycloakHttpClient::class)
            ->args([
                $config['base_url'],
                $config['client_id'],
                $config['client_secret'],
                $httpClientRef,
            ]);

        $services->alias(KeycloakHttpClientInterface::class, KeycloakHttpClient::class);

        $services
            ->set(KeycloakService::class)
            ->args([
                service(KeycloakHttpClientInterface::class),
            ]);

        $services->alias(KeycloakServiceInterface::class, KeycloakService::class);
    }
}
