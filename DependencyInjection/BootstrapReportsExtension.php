<?php

namespace Eidsonator\BootstrapReportsBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */;
class BootstrapReportsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('reportDirectory', $config['report_directory']);
        $container->setParameter('dashboardDirectory', $config['dashboard_directory']);
        $container->setParameter('default_file_extension_mapping', $config['default_file_extension_mapping']);
        $container->setParameter('environments', $config['environments']);
        $container->setParameter('report_formats', $config['report_formats']);
        $container->setParameter('mail_settings', $config['mail_settings']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
