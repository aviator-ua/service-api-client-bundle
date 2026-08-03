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

namespace Auto1\ServiceAPIClientBundle\Tests\Service\Request\BodyFactory;

use Auto1\ServiceAPIClientBundle\Service\Request\BodyFactory\RequestBodyFactoryInterface;
use Auto1\ServiceAPIClientBundle\Service\Request\BodyFactory\RequestBodyFactoryRegistry;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIRequest\ServiceRequestInterface;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Class RequestBodyFactoryRegistryTest.
 */
class RequestBodyFactoryRegistryTest extends TestCase
{
    /**
     * @return void
     */
    public function testGetFactoryReturnsTheFirstSupportingFactory(): void
    {
        $request = $this->createMock(ServiceRequestInterface::class);
        $endpoint = $this->createMock(EndpointInterface::class);

        $skipped = $this->factory(false);
        $skipped->expects(self::never())->method('create');

        $expected = $this->factory(true);

        $registry = new RequestBodyFactoryRegistry([$skipped, $expected, $this->factory(true)]);

        self::assertSame($expected, $registry->getFactory($request, $endpoint));
    }

    /**
     * @return void
     */
    public function testGetFactoryThrowsWhenNoFactorySupportsTheRequest(): void
    {
        $registry = new RequestBodyFactoryRegistry([$this->factory(false)]);

        $endpoint = $this->createMock(EndpointInterface::class);
        $endpoint->method('getRequestFormat')->willReturn('unknown');

        $this->expectException(LogicException::class);

        $registry->getFactory($this->createMock(ServiceRequestInterface::class), $endpoint);
    }

    /**
     * @param bool $supports
     *
     * @return RequestBodyFactoryInterface
     */
    private function factory(bool $supports): RequestBodyFactoryInterface
    {
        $factory = $this->createMock(RequestBodyFactoryInterface::class);
        $factory->method('supports')->willReturn($supports);

        return $factory;
    }
}
