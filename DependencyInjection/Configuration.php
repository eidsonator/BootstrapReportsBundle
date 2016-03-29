<?php

namespace Eidsonator\BootstrapReportsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('bootstrap_reports');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        $rootNode
            ->children()
                ->scalarNode('report_directory')->end()
                ->scalarNode('dashboard_directory')->end()
                ->arrayNode('default_file_extension_mapping')
                    ->children()
                        ->scalarNode('sql')->end()
                        ->scalarNode('php')->end()
                        ->scalarNode('js')->end()
                        ->scalarNode('ado')->end()
                    ->end()
                ->end()
                ->arrayNode('environments')
                    ->children()
                        ->arrayNode('prod')
                            ->children()
                                ->arrayNode('mysql')
                                    ->children()
                                        ->scalarNode('host')->end()
                                        ->scalarNode('user')->end()
                                        ->scalarNode('pass')->end()
                                        ->scalarNode('database')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('ado')
                                    ->children()
                                        ->scalarNode('uri')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('mongo')
                                    ->children()
                                        ->scalarNode('host')->end()
                                        ->scalarNode('port')->end()
                                        ->scalarNode('path')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('dev')
                            ->children()
                                ->arrayNode('mysql')
                                    ->children()
                                        ->scalarNode('host')->end()
                                        ->scalarNode('user')->end()
                                        ->scalarNode('pass')->end()
                                        ->scalarNode('database')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('ado')
                                    ->children()
                                        ->scalarNode('uri')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('mongo')
                                    ->children()
                                        ->scalarNode('host')->end()
                                        ->scalarNode('port')->end()
                                        ->scalarNode('path')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('test')
                            ->children()
                                ->arrayNode('mysql')
                                    ->children()
                                        ->scalarNode('host')->end()
                                        ->scalarNode('user')->end()
                                        ->scalarNode('pass')->end()
                                        ->scalarNode('database')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('ado')
                                    ->children()
                                        ->scalarNode('uri')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('mongo')
                                    ->children()
                                        ->scalarNode('host')->end()
                                        ->scalarNode('port')->end()
                                        ->scalarNode('path')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('report_formats')
                    ->children()
                        ->scalarNode('csv')->end()
                        ->scalarNode('xlsx')->end()
                        ->scalarNode('xls')->end()
                        ->scalarNode('text')->end()
                        ->scalarNode('table')->end()
                        ->scalarNode('raw_data')->end()
                        ->scalarNode('json')->end()
                        ->scalarNode('xml')->end()
                        ->scalarNode('sql')->end()
                        ->scalarNode('debug')->end()
                        ->scalarNode('raw')->end()
                    ->end()
                ->end()
                ->arrayNode('mail_settings')
                    ->children()
                        ->scalarNode('enabled')->end()
                        ->scalarNode('from')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
