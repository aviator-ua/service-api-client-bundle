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

namespace Auto1\ServiceAPIClientBundle\Tests\Integration;

use Auto1\ServiceAPIClientBundle\Service\Request\RequestFactoryInterface;
use Auto1\ServiceAPIClientBundle\Tests\Integration\Fixtures\JsonRequestStub;
use Auto1\ServiceAPIClientBundle\Tests\Integration\Kernel\MultipartTestKernel;
use Auto1\ServiceAPIClientBundle\Tests\Service\Request\Multipart\Fixtures\MetadataStream;
use Auto1\ServiceAPIClientBundle\Tests\Service\Request\Multipart\Fixtures\MultipartRequestStub;
use Auto1\ServiceAPIClientBundle\Tests\Service\Request\Multipart\Fixtures\NestedObjectStub;
use Http\Message\Formatter\FullHttpMessageFormatter;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: boots a real kernel (FrameworkBundle + components + client
 * bundle) and builds a multipart request through the fully-wired services. Booting
 * the kernel also verifies the bundle loads and the container compiles cleanly.
 */
class RequestBuildingKernelTest extends TestCase
{
    /**
     * @var MultipartTestKernel
     */
    private $kernel;

    private static $boundaryPlaceholder = 'BOUNDARY';

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->kernel = new MultipartTestKernel('test', true);
        $this->kernel->boot();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    /**
     * @return void
     */
    public function testTheContainerCompilesAndExposesTheRequestFactory(): void
    {
        $requestFactory = $this->kernel->getContainer()->get('test.request_factory');

        self::assertInstanceOf(RequestFactoryInterface::class, $requestFactory);
    }

    public function testItBuildsAMultipartRequestThroughTheWiredServices(): void
    {
        $streamFactory = new Psr17Factory();
        $createdAt = new \DateTimeImmutable('2024-01-02 03:04:05', new \DateTimeZone('UTC'));
        $fileStream = $streamFactory->createStream('PNG-CONTENT');
        $file = new MetadataStream($fileStream, 'photo.png', 'image/png');

        $serviceRequest = (new MultipartRequestStub())
            ->setFile($file)
            ->setDescription('Hello world')
            ->setCreatedAt($createdAt)
            ->setTags(['alpha', 'beta'])
            ->setOwner(new NestedObjectStub('Alice', 'A-1'))
            ->setDocuments([new NestedObjectStub('first', 'D-1'), new NestedObjectStub('second', 'D-2')]);

        /** @var RequestFactoryInterface $requestFactory */
        $requestFactory = $this->kernel->getContainer()->get('test.request_factory');

        $request = $requestFactory->create($serviceRequest);

        $contentType = $request->getHeaderLine('Content-Type');
        $contentTypeParts = explode('boundary=', $contentType);
        $boundary = $contentTypeParts[1];

        $formatter = new FullHttpMessageFormatter(null);
        $formatted = $formatter->formatRequest($request);
        $actual = str_replace($boundary, self::$boundaryPlaceholder, $formatted);

        $b = self::$boundaryPlaceholder;
        $expected = "POST /v1/documents HTTP/1.1\n"
            . "Host: localhost\n"
            . "Content-Type: multipart/form-data; boundary={$b}\n"
            . "Accept: application/json\n"
            . "\n"
            . "--{$b}\r\n"
            . "Content-Type: image/png\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"photo.png\"\r\n"
            . "\r\n"
            . "PNG-CONTENT\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"description\"\r\n"
            . "\r\n"
            . "Hello world\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"created_at\"\r\n"
            . "\r\n"
            . "2024-01-02T03:04:05+0000\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"tags[0]\"\r\n"
            . "\r\n"
            . "alpha\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"tags[1]\"\r\n"
            . "\r\n"
            . "beta\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"owner[label]\"\r\n"
            . "\r\n"
            . "Alice\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"owner[code]\"\r\n"
            . "\r\n"
            . "A-1\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"documents[0][label]\"\r\n"
            . "\r\n"
            . "first\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"documents[0][code]\"\r\n"
            . "\r\n"
            . "D-1\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"documents[1][label]\"\r\n"
            . "\r\n"
            . "second\r\n"
            . "--{$b}\r\n"
            . "Content-Disposition: form-data; name=\"documents[1][code]\"\r\n"
            . "\r\n"
            . "D-2\r\n"
            . "--{$b}--\r\n";

        self::assertSame($expected, $actual);
    }

    public function testItBuildsAJsonRequestThroughTheWiredServices(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-02 03:04:05', new \DateTimeZone('UTC'));

        $serviceRequest = (new JsonRequestStub())
            ->setName('Widget')
            ->setQuantity(3)
            ->setCreatedAt($createdAt);

        /** @var RequestFactoryInterface $requestFactory */
        $requestFactory = $this->kernel->getContainer()->get('test.request_factory');

        $request = $requestFactory->create($serviceRequest);

        $formatter = new FullHttpMessageFormatter(null);
        $actual = $formatter->formatRequest($request);

        $expected = "POST /v1/widgets HTTP/1.1\n"
            . "Host: localhost\n"
            . "Content-Type: application/json\n"
            . "Accept: application/json\n"
            . "\n"
            . '{"name":"Widget","quantity":3,"createdAt":"2024-01-02T03:04:05+0000"}';

        self::assertSame($expected, $actual);
    }
}
