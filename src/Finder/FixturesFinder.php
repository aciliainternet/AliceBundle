<?php

/*
 * This file is part of the Hautelook\AliceBundle package.
 *
 * (c) Baldur Rensch <brensch@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hautelook\AliceBundle\Finder;

use Symfony\Component\Finder\Finder as SymfonyFinder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
class FixturesFinder implements FixturesFinderInterface
{
    /**
     * @var string
     */
    private $bundleFixturesPath;

    /**
     * @param string $bundleFixturesPath Path in which fixtures files or loaders are expected to be found.
     */
    public function __construct($bundleFixturesPath)
    {
        $this->bundleFixturesPath = $bundleFixturesPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getFixtures(KernelInterface $kernel, array $bundles, $environment, $container = null)
    {
        $loadersPaths = $this->getLoadersPaths($bundles, $environment);

        // Add all fixtures to the new Doctrine loader
        $fixtures = [];
        foreach ($loadersPaths as $path) {
            $fixtures = array_merge($fixtures, $this->getFixturesFromDirectory($path, $container));
        }

        if (0 === count($fixtures)) {
            throw new \InvalidArgumentException(
                sprintf('Could not find any fixtures to load in: %s', "\n\n- ".implode("\n- ", $loadersPaths))
            );
        }

        // Get real fixtures path
        // Note: Fixtures returned are guaranteed to be unique here
        return $this->resolveFixtures($kernel, $fixtures);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveFixtures(KernelInterface $kernel, array $fixtures)
    {
        $resolvedFixtures = [];

        // Get real fixtures path
        foreach ($fixtures as $index => $fixture) {
            if ($fixture instanceof \SplFileInfo) {
                $filePath = $fixture->getRealPath();

                if (false === $filePath) {
                    throw new \RuntimeException(
                        sprintf(
                            'The file %s pointed by a %s instance was not found.',
                            (string) $fixture,
                            get_class($fixture)
                        )
                    );
                }
                $fixture = $filePath;
            }

            if (false === is_string($fixture)) {
                throw new \InvalidArgumentException(
                    'Expected fixtures passed to be either strings or a SplFileInfo instances.'
                );
            }

            if ('@' === $fixture[0]) {
                // If $kernel fails to resolve the resource, will throw a \InvalidArgumentException exception
                $realPath = $kernel->locateResource($fixture, null, true);
            } else {
                $realPath = realpath($fixture);
            }

            if (false === $realPath || false === file_exists($realPath)) {
                throw new \InvalidArgumentException(sprintf('The file "%s" was not found', $fixture));
            }

            if (false === is_file($realPath)) {
                throw new \InvalidArgumentException(
                    sprintf('Expected "%s to be a fixture file, got a directory instead.', $fixture)
                );
            }

            $resolvedFixtures[$realPath] = true;
        }

        return array_keys($resolvedFixtures);
    }

    /**
     * {@inheritdoc}
     */
    public function getFixturesFromDirectory($path, $container = null)
    {
        $fixtures = [];

        $finder = SymfonyFinder::create()->in($path)->depth(0)->files()->name('*.yml');
        foreach ($finder as $file) {
            /* @var SplFileInfo $file */
            $fixtures[$file->getRealPath()] = true;
        }

        return array_keys($fixtures);
    }

    /**
     * Gets paths to directories containing loaders and fixtures files.
     *
     * @param BundleInterface[] $bundles
     * @param string            $environment
     *
     * @return string[] Real paths to loaders.
     */
    protected function getLoadersPaths(array $bundles, $environment)
    {
        $environments = [
            lcfirst($environment) => true,
            ucfirst($environment) => true,
        ];

        $paths = [];
        foreach ($bundles as $bundle) {
            $path = sprintf('%s/%s', $bundle->getPath(), $this->bundleFixturesPath);
            if (true === file_exists($path)) {
                $paths[$path] = true;
                try {
                    $files = SymfonyFinder::create()->directories()->in($path);
                    foreach ($files as $file) {
                        /** @var SplFileInfo $file */
                        if (true === isset($environments[$file->getRelativePathname()])) {
                            $paths[$file->getRealPath()] = true;
                        }
                    }
                } catch (\InvalidArgumentException $exception) {
                }
            }
        }

        return array_keys($paths);
    }
}
