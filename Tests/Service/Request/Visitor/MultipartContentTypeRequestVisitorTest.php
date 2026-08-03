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

namespace Auto1\ServiceAPIClientBundle\Tests\Service\Request\Visitor;

use Auto1\ServiceAPIClientBundle\Service\Request\Visitor\MultipartContentTypeRequestVisitor;
use Auto1\ServiceAPIComponentsBundle\Exception\Request\MalformedRequestException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class MultipartContentTypeRequestVisitorTest.
 */
class MultipartContentTypeRequestVisitorTest extends TestCase
{
    /**
     * @var MultipartContentTypeRequestVisitor
     */
    private $visitor;

    /**
     * @var Psr17Factory
     */
    private $streamFactory;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->visitor = new MultipartContentTypeRequestVisitor();
        $this->streamFactory = new Psr17Factory();
    }

    /**
     * @return void
     */
    public function testVisitSetsContentTypeWithBoundaryReadFromBody(): void
    {
        $boundary = 'a1b2c3d4e5';
        $body = $this->streamFactory->createStream(
            "--{$boundary}\r\nContent-Disposition: form-data; name=\"x\"\r\n\r\nvalue\r\n--{$boundary}--\r\n"
        );

        $request = $this->createMock(RequestInterface::class);
        $request->method('getBody')->willReturn($body);
        $request
            ->expects(self::once())
            ->method('withHeader')
            ->with('Content-Type', 'multipart/form-data; boundary=' . $boundary)
            ->willReturnSelf();

        $this->visitor->visit($request);

        // body must be left rewound for the client to read it from the start
        self::assertSame(0, $body->tell());
    }

    /**
     * @return void
     */
    public function testVisitThrowsWhenBodyIsNotMultipart(): void
    {
        $body = $this->streamFactory->createStream('{"plain":"json"}');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getBody')->willReturn($body);
        $request->expects(self::never())->method('withHeader');

        $this->expectException(MalformedRequestException::class);

        $this->visitor->visit($request);
    }

    /**
     * @return void
     */
    public function testVisitThrowsWhenBodyIsNotSeekable(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('isSeekable')->willReturn(false);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getBody')->willReturn($body);
        $request->expects(self::never())->method('withHeader');

        $this->expectException(MalformedRequestException::class);

        $this->visitor->visit($request);
    }
}
