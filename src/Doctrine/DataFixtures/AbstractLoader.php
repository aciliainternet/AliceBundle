<?php

/*
 * This file is part of the Hautelook\AliceBundle package.
 *
 * (c) Baldur Rensch <brensch@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hautelook\AliceBundle\Doctrine\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Helper for declaring doctrine data loaders.
 *
 * @author Baldur Rensch <brensch@gmail.com>
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
abstract class AbstractLoader extends ContainerBuilder implements LoaderInterface
{
    /**
     * Loads the fixtures files.
     *
     * @param ObjectManager $objectManager
     *
     * @return \object[] Persisted objects
     */
    public function load(ObjectManager $objectManager)
    {
    }
}
