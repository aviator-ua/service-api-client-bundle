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

namespace Auto1\ServiceAPIClientBundle\Tests\DependencyInjection\CompilerPass;

use Auto1\ServiceAPIClientBundle\DependencyInjection\CompilerPass\LoggerFallbackCompilerPass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class LoggerFallbackCompilerPassTest.
 */
class LoggerFallbackCompilerPassTest extends TestCase
{
    /**
     * @var LoggerFallbackCompilerPass
     */
    private $pass;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->pass = new LoggerFallbackCompilerPass();
    }

    /**
     * @return void
     */
    public function testProcessRepointsTheAliasToTheApplicationLoggerWhenPresent(): void
    {
        $container = $this->containerWithDefaultAlias();
        $container->setDefinition('logger', new Definition(LoggerInterface::class));

        $this->pass->process($container);

        self::assertSame('logger', (string) $container->getAlias('auto1.api.logger'));
    }

    /**
     * @return void
     */
    public function testProcessKeepsTheNullLoggerFallbackWhenNoApplicationLoggerExists(): void
    {
        $container = $this->containerWithDefaultAlias();

        $this->pass->process($container);

        self::assertSame('auto1.api.logger.null', (string) $container->getAlias('auto1.api.logger'));
    }

    /**
     * Mirrors the default wiring from services.yml.
     *
     * @return ContainerBuilder
     */
    private function containerWithDefaultAlias(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('auto1.api.logger.null', new Definition(NullLogger::class));
        $container->setAlias('auto1.api.logger', 'auto1.api.logger.null');

        return $container;
    }
}
