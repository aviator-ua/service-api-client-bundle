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

namespace Auto1\ServiceAPIClientBundle\Service\Request\Visitor;

use Auto1\ServiceAPIComponentsBundle\Exception\Request\MalformedRequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Sets `Content-Type: multipart/form-data; boundary=<boundary>`.
 *
 * The boundary is recovered from the body itself: a multipart body always begins
 * with `--<boundary>\r\n`, so there is no shared state between the body builder
 * and this visitor. A body the boundary cannot be read from is an error — sending
 * a multipart request without a Content-Type boundary would only produce an opaque
 * 4xx on the server side.
 */
class MultipartContentTypeRequestVisitor implements RequestVisitorInterface
{
    private const HEADER_NAME = 'Content-Type';
    private const MEDIA_TYPE = 'multipart/form-data';

    /**
     * Bytes peeked from the head of the body to read the leading boundary line.
     */
    private const HEAD_BYTES = 512;

    /**
     * {@inheritdoc}
     */
    public function visit(RequestInterface $request): RequestInterface
    {
        $boundary = $this->extractBoundary($request->getBody());

        return $request->withHeader(
            self::HEADER_NAME,
            sprintf('%s; boundary=%s', self::MEDIA_TYPE, $boundary)
        );
    }

    /**
     * @param StreamInterface $body
     *
     * @return string
     */
    private function extractBoundary(StreamInterface $body): string
    {
        if (!$body->isSeekable()) {
            throw new MalformedRequestException(
                'multipart/form-data request body is not seekable, the boundary cannot be read from it.'
            );
        }

        $body->rewind();
        $head = $body->read(self::HEAD_BYTES);
        $body->rewind();

        if (0 !== strncmp($head, '--', 2)) {
            throw new MalformedRequestException(
                'multipart/form-data request body does not start with "--<boundary>"; '
                . 'expected a body built by the multipart stream factory.'
            );
        }

        $end = strpos($head, "\r\n");
        if (false === $end) {
            $end = strpos($head, "\n");
        }
        if (false === $end) {
            throw new MalformedRequestException(
                'multipart/form-data request body has no line break after the leading boundary.'
            );
        }

        return substr($head, 2, $end - 2);
    }
}