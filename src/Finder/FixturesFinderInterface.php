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

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class responsible retrieving the fixtures' paths to load.
 *
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
interface FixturesFinderInterface
{
    /**
     * Gets all fixtures files path.
     *
     * For first get all the path for where to look for fixtures.
     * For each path, will try to get fixtures from data loaders. If no data loader is found, will take all the
     * fixtures.
     *
     * @param KernelInterface   $kernel
     * @param BundleInterface[] $bundles
     * @param string            $environment
     *
     * @return string[] Fixtures files real paths.
     */
    public function getFixtures(KernelInterface $kernel, array $bundles, $environment, $container = null);

    /**
     * Gets the real path of each fixtures via the application kernel.
     *
     * @param KernelInterface         $kernel
     * @param string[]|\SplFileInfo[] $fixtures
     *
     * @return string[] Fixtures real path
     *
     * @throws \InvalidArgumentException Invalid file (got a directory or unsupported type)
     * @throws \RuntimeException         File could not be resolved.
     */
    public function resolveFixtures(KernelInterface $kernel, array $fixtures);

    /**
     * Get the fixtures path for a given directory. It is recommended not to take into account sub directories as
     * this function will be called for them later on.
     *
     * @param string $path Directory path
     *
     * @return string[]|\SplFileInfo[] Fixtures paths
     */
    public function getFixturesFromDirectory($path, $container = null);
}
