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

namespace Auto1\ServiceAPIClientBundle\Tests\Service\Request\Multipart\Fixtures;

use Psr\Http\Message\StreamInterface;

/**
 * Test double: a stream that carries `filename` / `mime-type` metadata, mirroring
 * the convention of the incoming side's UploadedFileStream.
 */
final class MetadataStream implements StreamInterface
{
    /**
     * @var StreamInterface
     */
    private $inner;

    /**
     * @var string|null
     */
    private $filename;

    /**
     * @var string|null
     */
    private $mimeType;

    public function __construct(StreamInterface $inner, ?string $filename, ?string $mimeType)
    {
        $this->inner = $inner;
        $this->filename = $filename;
        $this->mimeType = $mimeType;
    }

    public function getMetadata($key = null)
    {
        if ('filename' === $key) {
            return $this->filename;
        }

        if ('mime-type' === $key) {
            return $this->mimeType;
        }

        return $this->inner->getMetadata($key);
    }

    public function __toString(): string
    {
        return (string) $this->inner;
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function detach()
    {
        return $this->inner->detach();
    }

    public function getSize(): ?int
    {
        return $this->inner->getSize();
    }

    public function tell(): int
    {
        return $this->inner->tell();
    }

    public function eof(): bool
    {
        return $this->inner->eof();
    }

    public function isSeekable(): bool
    {
        return $this->inner->isSeekable();
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        $this->inner->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->inner->rewind();
    }

    public function isWritable(): bool
    {
        return $this->inner->isWritable();
    }

    public function write($string): int
    {
        return $this->inner->write($string);
    }

    public function isReadable(): bool
    {
        return $this->inner->isReadable();
    }

    public function read($length): string
    {
        return $this->inner->read($length);
    }

    public function getContents(): string
    {
        return $this->inner->getContents();
    }
}
