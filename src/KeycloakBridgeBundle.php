<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakJwtVerificationServiceInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakOidcAuthenticationServiceInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakRealmServiceInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceFactory;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserIdentifierAttributeServiceInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakUserManagementServiceInterface;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper;
use Apacheborys\SymfonyKeycloakBridgeBundle\Model\UserEntityConfig;
use Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtAuthenticator;
use Apacheborys\SymfonyKeycloakBridgeBundle\Service\ConfiguredKeycloakService;
use Override;
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
    #[Override]
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
                ->scalarNode('logger_service')->defaultNull()->end()
                ->booleanNode('allow_role_creation')->defaultFalse()->end()
                ->integerNode('realm_list_ttl')->min(0)->defaultValue(3600)->end()
                ->arrayNode('user_entities')
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('realm')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('user_identifier_field')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('attributes_map')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('property')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('attribute_name')->defaultNull()->end()
                                        ->scalarNode('jwt_claim_name')->defaultNull()->end()
                                        ->booleanNode('create_if_missing')->defaultFalse()->end()
                                    ->end()
                                ->end()
                                ->defaultValue([])
                            ->end()
                            ->scalarNode('role_prefix')->defaultValue('')->end()
                            ->scalarNode('role_suffix')->defaultValue('')->end()
                            ->scalarNode('mapper')->defaultValue(LocalEntityMapper::class)->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;
    }

    /**
     * @param array{
     *  base_url: string,
     *  client_realm: string,
     *  client_id: string,
     *  client_secret: string,
     *  http_client_service: string|null,
     *  request_factory_service: string|null,
     *  stream_factory_service: string|null,
     *  cache_pool: string|null,
     *  logger_service: string|null,
     *  allow_role_creation: bool,
     *  realm_list_ttl: int,
     *  user_entities: array<string, array{
     *      realm: string,
     *      user_identifier_field: string,
     *      attributes_map: list<array{
     *          property: string,
     *          attribute_name: string|null,
     *          jwt_claim_name: string|null,
     *          create_if_missing: bool
     *      }>,
     *      role_prefix: string,
     *      role_suffix: string,
     *      mapper: string
     *  }>
     * } $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder
            ->registerForAutoconfiguration(interface: LocalKeycloakUserBridgeMapperInterface::class)
            ->addTag(name: 'keycloak.local_user_mapper');

        $services = $container->services();
        $httpClientRef = is_string($config['http_client_service'])
            ? service(serviceId: $config['http_client_service'])
            : service(serviceId: ClientInterface::class);

        $requestFactoryRef = is_string($config['request_factory_service'])
            ? service(serviceId: $config['request_factory_service'])
            : service(serviceId: RequestFactoryInterface::class);

        $streamFactoryRef = is_string($config['stream_factory_service'])
            ? service(serviceId: $config['stream_factory_service'])
            : service(serviceId: StreamFactoryInterface::class);

        $cacheRef = is_string($config['cache_pool'])
            ? service(serviceId: $config['cache_pool'])
            : null;

        $loggerRef = is_string($config['logger_service'])
            ? service(serviceId: $config['logger_service'])
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

        $services->set(id: KeycloakServiceFactory::class);

        $services
            ->set(id: KeycloakService::class)
            ->factory(factory: [service(serviceId: KeycloakServiceFactory::class), 'create'])
            ->args(
                arguments: [
                    service(serviceId: KeycloakHttpClientInterface::class),
                    tagged_iterator(tag: 'keycloak.local_user_mapper'),
                    $loggerRef,
                    $config['allow_role_creation'],
                ]
            );

        $services
            ->set(id: ConfiguredKeycloakService::class)
            ->args(
                arguments: [
                    service(serviceId: KeycloakService::class),
                    tagged_iterator(tag: 'keycloak.user_entity_config'),
                ]
            );

        $services->alias(id: KeycloakServiceInterface::class, referencedId: ConfiguredKeycloakService::class);
        $services->alias(
            id: KeycloakUserManagementServiceInterface::class,
            referencedId: ConfiguredKeycloakService::class
        );
        $services->alias(
            id: KeycloakUserIdentifierAttributeServiceInterface::class,
            referencedId: ConfiguredKeycloakService::class
        );
        $services->alias(
            id: KeycloakOidcAuthenticationServiceInterface::class,
            referencedId: ConfiguredKeycloakService::class
        );
        $services->alias(
            id: KeycloakJwtVerificationServiceInterface::class,
            referencedId: ConfiguredKeycloakService::class
        );
        $services->alias(id: KeycloakRealmServiceInterface::class, referencedId: ConfiguredKeycloakService::class);

        $services
            ->set(id: KeycloakJwtAuthenticator::class)
            ->args(
                arguments: [
                    service(serviceId: KeycloakJwtVerificationServiceInterface::class),
                    service(serviceId: KeycloakClientConfig::class),
                    tagged_iterator(tag: 'keycloak.user_entity_config'),
                ]
            );

        $services->alias(id: 'keycloak.jwt_authenticator', referencedId: KeycloakJwtAuthenticator::class);

        if ($config['user_entities'] === []) {
            return;
        }

        $configuredMapperClasses = [];
        foreach ($config['user_entities'] as $className => $userEntityConfig) {
            $normalizedClassName = str_replace('\\\\', '\\', $className);
            $mapperClass = str_replace('\\\\', '\\', $userEntityConfig['mapper']);

            $services
                ->set(
                    id: 'keycloak_bridge.user_entity_config.' . str_replace('\\', '_', $normalizedClassName),
                    class: UserEntityConfig::class
                )
                ->args(
                    arguments: [
                        $userEntityConfig['realm'],
                        $normalizedClassName,
                        $userEntityConfig['user_identifier_field'],
                        $userEntityConfig['role_prefix'],
                        $userEntityConfig['role_suffix'],
                        $mapperClass,
                        $userEntityConfig['attributes_map'],
                    ]
                )
                ->tag(name: 'keycloak.user_entity_config');

            $configuredMapperClasses[$mapperClass] = true;
        }

        if (isset($configuredMapperClasses[LocalEntityMapper::class])) {
            $services
                ->set(id: LocalEntityMapper::class)
                ->args(
                    arguments: [
                        tagged_iterator(tag: 'keycloak.user_entity_config'),
                        $config['client_id'],
                        $config['client_secret'],
                    ]
                )
                ->tag(name: 'keycloak.local_user_mapper');
        }

        foreach (array_keys($configuredMapperClasses) as $mapperClass) {
            if ($mapperClass === LocalEntityMapper::class) {
                continue;
            }

            if ($builder->hasDefinition($mapperClass)) {
                $definition = $builder->getDefinition($mapperClass);
                if (!$definition->hasTag('keycloak.local_user_mapper')) {
                    $definition->addTag('keycloak.local_user_mapper');
                }

                continue;
            }

            if ($builder->hasAlias($mapperClass)) {
                continue;
            }

            $services
                ->set(id: $mapperClass, class: $mapperClass)
                ->autowire()
                ->autoconfigure()
                ->tag(name: 'keycloak.local_user_mapper');
        }
    }
}
