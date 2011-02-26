<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\CacheWarmer;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * The hydrator generator cache warmer generates all document hydrators.
 *
 * In the process of generating hydrators the cache for all the metadata is primed also,
 * since this information is necessary to build the hydrators in the first place.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class HydratorCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * This cache warmer is not optional, without hydrators fatal error occurs!
     *
     * @return false
     */
    public function isOptional()
    {
        return false;
    }

    public function warmUp($cacheDir)
    {
        // we need the directory no matter the hydrator cache generation strategy.
        $hydratorCacheDir = $this->container->getParameter('doctrine.odm.mongodb.hydrator_dir');
        if (!file_exists($hydratorCacheDir)) {
            if (false === @mkdir($hydratorCacheDir, 0777, true)) {
                throw new \RuntimeException(sprintf('Unable to create the Doctrine Hydrator directory (%s)', dirname($hydratorCacheDir)));
            }
        } else if (!is_writable($hydratorCacheDir)) {
            throw new \RuntimeException(sprintf('Doctrine Hydrator directory (%s) is not writeable for the current system user.', $hydratorCacheDir));
        }

        // if hydrators are autogenerated we don't need to generate them in the cache warmer.
        if ($this->container->getParameter('doctrine.odm.mongodb.auto_generate_hydrator_classes') === true) {
            return;
        }

        $documentManagers = $this->container->getParameter('doctrine.odm.mongodb.document_managers');
        foreach ($documentManagers AS $documentManagerName) {
            $dm = $this->container->get(sprintf('doctrine.odm.mongodb.%s_document_manager', $documentManagerName));
            /* @var $dm Doctrine\ODM\MongoDB\DocumentManager */
            $classes = $dm->getMetadataFactory()->getAllMetadata();
            $dm->getHydratorFactory()->generateHydratorClasses($classes);
        }
    }
}