<?php

/*
 * This file is part of the Hautelook\AliceBundle package.
 *
 * (c) Baldur Rensch <brensch@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hautelook\AliceBundle\Doctrine\Finder;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Hautelook\AliceBundle\Doctrine\DataFixtures\LoaderInterface;
use Symfony\Component\Finder\Finder as SymfonyFinder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Extends its parent class to take into account doctrine data loaders.
 *
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
class FixturesFinder extends \Hautelook\AliceBundle\Finder\FixturesFinder
{
    /**
     * {@inheritdoc}
     *
     * Extended to look for data loaders. If a data loader is found, will take the fixtures from it instead of taking
     * all the fixtures files.
     */
    public function getFixturesFromDirectory($path, $container = null)
    {
        $fixtures = [];

        $loaders = $this->getDataLoadersFromDirectory($path, $container);
        foreach ($loaders as $loader) {
            $fixtures = array_merge($fixtures, $loader->getFixtures());
        }

        // If no data loader is found, takes all fixtures files
        if (0 === count($loaders)) {
            return parent::getFixturesFromDirectory($path, $container);
        }

        return $fixtures;
    }

    /**
     * Gets all data loaders instances.
     *
     * For first get all the path for where to look for data loaders.
     *
     * @param BundleInterface[] $bundles
     * @param string            $environment
     *
     * @return LoaderInterface[] Fixtures files real paths.
     */
    public function getDataLoaders(array $bundles, $environment)
    {
        $loadersPaths = $this->getLoadersPaths($bundles, $environment);

        // Add all fixtures to the new Doctrine loader
        $loaders = [];
        foreach ($loadersPaths as $path) {
            $loaders = array_merge($loaders, $this->getDataLoadersFromDirectory($path));
        }

        return $loaders;
    }

    /**
     * Get data loaders inside the given directory.
     *
     * @param string $path Directory path
     *
     * @return LoaderInterface[]
     */
    private function getDataLoadersFromDirectory($path, $container = null)
    {
        $loaders = [];

        // Get all PHP classes in given folder
        $phpClasses = [];
        $finder = SymfonyFinder::create()->depth(0)->in($path)->files()->name('*.php');
        foreach ($finder as $file) {
            /* @var SplFileInfo $file */
            $phpClasses[$file->getRealPath()] = true;
            require_once $file->getRealPath();
        }

        // Check if PHP classes are data loaders or not
        foreach (get_declared_classes() as $className) {
            $reflectionClass = new \ReflectionClass($className);
            $sourceFile = $reflectionClass->getFileName();

            if (true === isset($phpClasses[$sourceFile])) {
                if ($reflectionClass->implementsInterface('Hautelook\AliceBundle\Doctrine\DataFixtures\LoaderInterface')) {
                    $loaders[$className] = new $className();

                    if ($loaders[$className] instanceof ContainerAwareInterface) {
                        $loaders[$className]->setContainer($container);
                    }
                }
            }
        }

        return $loaders;
    }
}
