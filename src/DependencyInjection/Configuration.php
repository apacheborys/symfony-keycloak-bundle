<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('keycloak_bridge');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('base_url')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('username')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('password')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('http_client_service')->defaultNull()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
