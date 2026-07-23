<?php
/*
* This file is part of the auto1-oss/service-api-client-bundle.
*
* (c) AUTO1 Group SE https://www.auto1-group.com
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace Auto1\ServiceAPIClientBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Points the bundle-local `auto1.api.logger` alias at the application's `logger`
 * service when it exists (e.g. MonologBundle). Otherwise the alias keeps its
 * default target — a bundle-local NullLogger — so the bundle still compiles in an
 * application that provides no logger.
 */
class LoggerFallbackCompilerPass implements CompilerPassInterface
{
    const ALIAS = 'auto1.api.logger';
    const LOGGER = 'logger';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(self::LOGGER)) {
            return;
        }

        $container->setAlias(self::ALIAS, self::LOGGER);
    }
}
