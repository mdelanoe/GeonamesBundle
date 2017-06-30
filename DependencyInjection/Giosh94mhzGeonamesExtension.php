<?php

namespace Giosh94mhz\GeonamesBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class Giosh94mhzGeonamesExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $this->setPersistenceLayer($config, $container, $loader);

        $this->setImportConfig($config, $container, $loader);

        $this->setGeocoder($config, $container, $loader);
    }

    private function setPersistenceLayer(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $container->setParameter(
            'giosh94mhz_geonames.orm.object_manager_name',
            $config['orm']['object_manager_name']
        );

        $loader->load('doctrine.xml');
    }

    private function setImportConfig(array $config, ContainerBuilder $container)
    {
        /*
         * DOWNLOAD
         */
        $container->setParameter(
            'giosh94mhz_geonames.download.directory',
            rtrim($config['download']['directory'], DIRECTORY_SEPARATOR)
        );

        $container->setParameter(
            'giosh94mhz_geonames.download.adapter',
            $container->getParameter("giosh94mhz_geonames.import.download_adapter.{$config['download']['adapter']}.class")
        );

        /*
         * FEATURES
         */
        $forcedFeatureInclude=array();
        if ($config['country']['enabled']) {
            $forcedFeatureInclude[]='A.PC*';
        }
        if ($config['admin1']['enabled']) {
            $forcedFeatureInclude[]='A.ADM1';
        }
        if ($config['admin2']['enabled']) {
            $forcedFeatureInclude[]='A.ADM2';
        }
        if ( !empty($forcedFeatureInclude) && $config['continent']['enabled']) {
            $forcedFeatureInclude[]='L.CONT';
        }

        $container->setParameter(
            'giosh94mhz_geonames.feature.locale',
            $config['feature']['locale']
        );

        $container->setParameter(
            'giosh94mhz_geonames.feature.include',
            $config['feature']['include']
        );

        $container->setParameter(
            'giosh94mhz_geonames.feature.exclude',
            $config['feature']['exclude']
        );

        $container->setParameter(
            'giosh94mhz_geonames.feature.forced_include',
            $forcedFeatureInclude
        );

        /*
         * TOPONYM
         */
        $container->setParameter(
            'giosh94mhz_geonames.toponym.all',
            $config['toponym']['all']
        );

        $container->setParameter(
            'giosh94mhz_geonames.toponym.cities',
            $config['toponym']['cities']
        );

        $container->setParameter(
            'giosh94mhz_geonames.toponym.countries',
            $config['toponym']['countries']
        );

        $container->setParameter(
            'giosh94mhz_geonames.toponym.options.alternate_names',
            $config['toponym']['options']['alternate_names']
        );

        $container->setParameter(
            'giosh94mhz_geonames.toponym.options.alternate_country_codes',
            $config['toponym']['options']['alternate_country_codes']
        );

        /*
         * OTHERS
         */
        $container->setParameter(
            'giosh94mhz_geonames.continent.enabled',
            $config['continent']['enabled']
        );

        $container->setParameter(
            'giosh94mhz_geonames.country.enabled',
            $config['country']['enabled']
        );

        $container->setParameter(
            'giosh94mhz_geonames.admin1.enabled',
            $config['admin1']['enabled']
        );

        $container->setParameter(
            'giosh94mhz_geonames.admin2.enabled',
            $config['admin2']['enabled']
        );

        $container->setParameter(
            'giosh94mhz_geonames.alternate_names.enabled',
            $config['alternate_names']['enabled']
        );

        $container->setParameter(
            'giosh94mhz_geonames.hierarchy.enabled',
            $config['hierarchy']['enabled']
        );

        /*
         * IMPORT ALL TAGS
         */
        if ($config['continent']['enabled'])
            $container->getDefinition('giosh94mhz_geonames.import.step.continent')->addTag('giosh94mhz_geonames.import.all_steps');
        if ($config['country']['enabled'])
            $container->getDefinition('giosh94mhz_geonames.import.step.country')->addTag('giosh94mhz_geonames.import.all_steps');
        if ($config['admin1']['enabled'])
            $container->getDefinition('giosh94mhz_geonames.import.step.admin1')->addTag('giosh94mhz_geonames.import.all_steps');
        if ($config['admin2']['enabled'])
            $container->getDefinition('giosh94mhz_geonames.import.step.admin2')->addTag('giosh94mhz_geonames.import.all_steps');
        if ($config['hierarchy']['enabled'])
            $container->getDefinition('giosh94mhz_geonames.import.step.hierarchy')->addTag('giosh94mhz_geonames.import.all_steps');
    }

    private function setGeocoder(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $container->setParameter(
            'giosh94mhz_geonames.geocoder.enabled',
            $config['geocoder']['enabled']
        );

        if(! $config['geocoder']['enabled'])
            return;

        $loader->load('geocoder.xml');

        sort($config['geocoder']['search_distances'], SORT_NUMERIC);
        $provider = $container->getDefinition('giosh94mhz_geonames.geocoder.persistent_geonames_provider');
        $provider->addMethodCall('setSearchDistances', array(
            $config['geocoder']['search_distances']

        ));

        $container->setParameter(
            'giosh94mhz_geonames.geocoder.ip_provider',
            $config['geocoder']['ip_provider']
        );
    }
}
