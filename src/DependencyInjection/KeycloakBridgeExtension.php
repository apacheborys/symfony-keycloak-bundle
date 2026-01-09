<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\DependencyInjection;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class KeycloakBridgeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $httpClientRef = null;
        if ($config['http_client_service'] !== null) {
            $httpClientRef = new Reference($config['http_client_service']);
        }

        $httpClientDefinition = new Definition(KeycloakHttpClient::class);
        $httpClientDefinition->setArguments([
            $config['base_url'],
            $config['client_id'],
            $config['client_secret'],
            $config['username'],
            $config['password'],
            $httpClientRef,
        ]);

        $container->setDefinition(KeycloakHttpClient::class, $httpClientDefinition);
        $container->setAlias(KeycloakHttpClientInterface::class, KeycloakHttpClient::class);

        $serviceDefinition = new Definition(KeycloakService::class);
        $serviceDefinition->setArguments([
            new Reference(KeycloakHttpClientInterface::class),
        ]);

        $container->setDefinition(KeycloakService::class, $serviceDefinition);
        $container->setAlias(KeycloakServiceInterface::class, KeycloakService::class);
    }
}
