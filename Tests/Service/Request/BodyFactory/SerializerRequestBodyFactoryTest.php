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

use Auto1\ServiceAPIClientBundle\Service\Request\BodyFactory\SerializerRequestBodyFactory;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIRequest\ServiceRequestInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

class SerializerRequestBodyFactoryTest extends TestCase
{
    private static $serializedBody = 'serialized-body';

    public function testSupportsReturnsTrueForEveryRequest(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $request = $this->createMock(ServiceRequestInterface::class);
        $endpoint = $this->createMock(EndpointInterface::class);

        $factory = new SerializerRequestBodyFactory($serializer);

        $supports = $factory->supports($request, $endpoint);

        self::assertTrue($supports);
    }

    /**
     * @dataProvider formatProvider
     */
    public function testCreateSerializesWithTheEndpointRequestFormat(string $format): void
    {
        $request = $this->createMock(ServiceRequestInterface::class);
        $endpoint = $this->createMock(EndpointInterface::class);
        $endpoint->method('getRequestFormat')->willReturn($format);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('serialize')
            ->with($request, $format)
            ->willReturn(self::$serializedBody);

        $factory = new SerializerRequestBodyFactory($serializer);

        $body = $factory->create($request, $endpoint);

        self::assertSame(self::$serializedBody, $body);
    }

    public function formatProvider(): array
    {
        return [
            'json' => ['json'],
            'url' => ['url'],
            'json-patch' => ['json-patch'],
        ];
    }
}
