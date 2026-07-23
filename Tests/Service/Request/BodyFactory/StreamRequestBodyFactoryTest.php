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

use Auto1\ServiceAPIClientBundle\Service\Request\BodyFactory\StreamRequestBodyFactory;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIRequest\ServiceRequestInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

interface StreamServiceRequest extends ServiceRequestInterface, StreamInterface
{
}

class StreamRequestBodyFactoryTest extends TestCase
{
    public function testItSupportsAStreamRequest(): void
    {
        $request = $this->createMock(StreamServiceRequest::class);
        $endpoint = $this->createMock(EndpointInterface::class);

        $factory = new StreamRequestBodyFactory();

        $supports = $factory->supports($request, $endpoint);

        self::assertTrue($supports);
    }

    public function testItDoesNotSupportANonStreamRequest(): void
    {
        $request = $this->createMock(ServiceRequestInterface::class);
        $endpoint = $this->createMock(EndpointInterface::class);

        $factory = new StreamRequestBodyFactory();

        $supports = $factory->supports($request, $endpoint);

        self::assertFalse($supports);
    }

    public function testItReturnsTheRequestStreamUnchanged(): void
    {
        $request = $this->createMock(StreamServiceRequest::class);
        $endpoint = $this->createMock(EndpointInterface::class);

        $factory = new StreamRequestBodyFactory();

        $body = $factory->create($request, $endpoint);

        self::assertSame($request, $body);
    }
}
