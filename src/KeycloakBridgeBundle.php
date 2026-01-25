<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class KeycloakBridgeBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition
            ->rootNode()
            ->children()
                ->scalarNode('base_url')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_realm')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('http_client_service')->defaultNull()->end()
                ->scalarNode('request_factory_service')->defaultNull()->end()
                ->scalarNode('stream_factory_service')->defaultNull()->end()
                ->scalarNode('cache_pool')->defaultNull()->end()
                ->integerNode('realm_list_ttl')->min(0)->defaultValue(3600)->end()
                ->arrayNode('user_entities')
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('realm')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder
            ->registerForAutoconfiguration(interface: LocalKeycloakUserBridgeMapperInterface::class)
            ->addTag(name: 'keycloak.local_user_mapper');

        $services = $container->services();
        $httpClientRef = $config['http_client_service'] !== null
            ? service(serviceId: $config['http_client_service'])
            : service(serviceId: ClientInterface::class);
        $requestFactoryRef = $config['request_factory_service'] !== null
            ? service(serviceId: $config['request_factory_service'])
            : service(serviceId: RequestFactoryInterface::class);
        $streamFactoryRef = $config['stream_factory_service'] !== null
            ? service(serviceId: $config['stream_factory_service'])
            : service(serviceId: StreamFactoryInterface::class);
        $cacheRef = $config['cache_pool'] !== null
            ? service(serviceId: $config['cache_pool'])
            : null;

        $services
            ->set(id: KeycloakClientConfig::class)
            ->args(
                arguments: [
                    $config['base_url'],
                    $config['client_realm'],
                    $config['client_id'],
                    $config['client_secret'],
                    $config['realm_list_ttl'],
                ]
            );

        $services->set(id: KeycloakHttpClientFactory::class);

        $services
            ->set(id: KeycloakHttpClient::class)
            ->factory(factory: [service(serviceId: KeycloakHttpClientFactory::class), 'create'])
            ->args(
                arguments: [
                    service(serviceId: KeycloakClientConfig::class),
                    $httpClientRef,
                    $requestFactoryRef,
                    $streamFactoryRef,
                    $cacheRef,
                ]
            );

        $services->alias(id: KeycloakHttpClientInterface::class, referencedId: KeycloakHttpClient::class);

        $services
            ->set(id: KeycloakService::class)
            ->args(
                arguments: [
                    service(serviceId: KeycloakHttpClientInterface::class),
                    tagged_iterator(tag: 'keycloak.local_user_mapper'),
                ]
            );

        $services->alias(id: KeycloakServiceInterface::class, referencedId: KeycloakService::class);

        if ($config['user_entities'] === []) {
            return;
        }

        foreach ($config['user_entities'] as $className => $userEntityConfig) {
            $services
                ->set(id: 'keycloak_bridge.user_entity_config.' . str_replace('\\', '_', $className), class: UserEntityConfig::class)
                ->args(
                    arguments: [
                        $userEntityConfig['realm'],
                        $className,
                    ]
                )
                ->tag(name: 'keycloak.user_entity_config');
        }

        $services
            ->set(id: LocalEntityMapper::class)
            ->args(arguments: [tagged_iterator(tag: 'keycloak.user_entity_config')])
            ->tag(name: 'keycloak.local_user_mapper');
    }
}
