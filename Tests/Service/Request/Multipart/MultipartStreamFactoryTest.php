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

namespace Auto1\ServiceAPIClientBundle\Tests\Service\Request\Multipart;

use Auto1\ServiceAPIClientBundle\Service\Request\Multipart\MultipartStreamFactory;
use Auto1\ServiceAPIClientBundle\Tests\Service\Request\Multipart\Fixtures\MetadataStream;
use Auto1\ServiceAPIClientBundle\Tests\Service\Request\Multipart\Fixtures\MultipartRequestStub;
use Auto1\ServiceAPIClientBundle\Tests\Service\Request\Multipart\Fixtures\NestedObjectStub;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class MultipartStreamFactoryTest.
 *
 * The normalizer is a mocked collaborator: it is stubbed to return controlled
 * (deep) normalized output so the assertions target the factory's own logic —
 * file-part building and the flattening of normalized values into `name[child]`
 * field parts — rather than Symfony's normalization behaviour.
 */
class MultipartStreamFactoryTest extends TestCase
{
    /**
     * @var Psr17Factory
     */
    private $streamFactory;

    /**
     * @var MultipartStreamFactory
     */
    private $factory;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->streamFactory = new Psr17Factory();
        $this->factory = new MultipartStreamFactory(
            $this->streamFactory,
            $this->normalizerStub(),
            PropertyAccess::createPropertyAccessor(),
            new CamelCaseToSnakeCaseNameConverter(null, false)
        );
    }

    /**
     * @return void
     */
    public function testItBuildsFilePartsAndFieldParts(): void
    {
        $request = (new MultipartRequestStub())
            ->setFile(new MetadataStream($this->streamFactory->createStream('PNG-CONTENT'), 'photo.png', 'image/png'))
            ->setCoverImage($this->streamFactory->createStream('COVER-CONTENT'))
            ->setDescription('Hello world')
            ->setVersion(42)
            ->setEnabled(true);

        $body = (string) $this->factory->create($request);

        // file part: filename + content-type taken from stream metadata
        self::assertStringContainsString('name="file"; filename="photo.png"', $body);
        self::assertStringContainsString('Content-Type: image/png', $body);
        self::assertStringContainsString('PNG-CONTENT', $body);

        // file part without metadata: filename falls back to the (snake_cased) field name, octet-stream
        self::assertStringContainsString('name="cover_image"; filename="cover_image"', $body);
        self::assertStringContainsString('Content-Type: application/octet-stream', $body);
        self::assertStringContainsString('COVER-CONTENT', $body);

        // scalar fields
        self::assertStringContainsString('name="description"', $body);
        self::assertStringContainsString('Hello world', $body);
        self::assertStringContainsString('name="version"', $body);
        self::assertStringContainsString('42', $body);
        self::assertStringContainsString('name="enabled"', $body);
        self::assertStringContainsString('true', $body);
    }

    /**
     * @return void
     */
    public function testItStringifiesValuesThroughTheNormalizer(): void
    {
        $request = (new MultipartRequestStub())
            ->setCreatedAt(new \DateTimeImmutable('2024-01-02 03:04:05'));

        $body = (string) $this->factory->create($request);

        self::assertStringContainsString('name="created_at"', $body);
        self::assertStringContainsString('2024-01-02', $body);
    }

    /**
     * @return void
     */
    public function testItFlattensAnArrayOfScalars(): void
    {
        $request = (new MultipartRequestStub())
            ->setTags(['alpha', 'beta']);

        $body = (string) $this->factory->create($request);

        self::assertStringContainsString('name="tags[0]"', $body);
        self::assertStringContainsString('alpha', $body);
        self::assertStringContainsString('name="tags[1]"', $body);
        self::assertStringContainsString('beta', $body);
    }

    /**
     * @return void
     */
    public function testItFlattensANestedObject(): void
    {
        $request = (new MultipartRequestStub())
            ->setOwner(new NestedObjectStub('Alice', 'A-1'));

        $body = (string) $this->factory->create($request);

        self::assertStringContainsString('name="owner[label]"', $body);
        self::assertStringContainsString('Alice', $body);
        self::assertStringContainsString('name="owner[code]"', $body);
        self::assertStringContainsString('A-1', $body);
    }

    /**
     * @return void
     */
    public function testItFlattensAnArrayOfObjects(): void
    {
        $request = (new MultipartRequestStub())
            ->setDocuments([new NestedObjectStub('first', 'D-1'), new NestedObjectStub('second', 'D-2')]);

        $body = (string) $this->factory->create($request);

        self::assertStringContainsString('name="documents[0][label]"', $body);
        self::assertStringContainsString('first', $body);
        self::assertStringContainsString('name="documents[1][code]"', $body);
        self::assertStringContainsString('D-2', $body);
    }

    /**
     * A `files[]`-shaped property: a collection of streams must become individual file
     * parts (`attachments[0]`, `attachments[1]`) carrying filename / Content-Type, rather
     * than being stringified by the normalizer into plain field parts.
     *
     * @return void
     */
    public function testItBuildsFilePartsForACollectionOfStreams(): void
    {
        $request = (new MultipartRequestStub())
            ->setAttachments([
                new MetadataStream($this->streamFactory->createStream('FIRST'), 'a.txt', 'text/plain'),
                new MetadataStream($this->streamFactory->createStream('SECOND'), 'b.txt', 'text/plain'),
            ]);

        $body = (string) $this->factory->create($request);

        self::assertStringContainsString('name="attachments[0]"; filename="a.txt"', $body);
        self::assertStringContainsString('FIRST', $body);
        self::assertStringContainsString('name="attachments[1]"; filename="b.txt"', $body);
        self::assertStringContainsString('SECOND', $body);
        self::assertStringContainsString('Content-Type: text/plain', $body);
    }

    /**
     * @return void
     */
    public function testItSkipsNullProperties(): void
    {
        $request = (new MultipartRequestStub())
            ->setFile($this->streamFactory->createStream('PNG-CONTENT'));

        $body = (string) $this->factory->create($request);

        self::assertStringContainsString('name="file"', $body);
        self::assertStringNotContainsString('name="description"', $body);
        self::assertStringNotContainsString('name="version"', $body);
        self::assertStringNotContainsString('name="enabled"', $body);
        self::assertStringNotContainsString('name="cover_image"', $body);
    }

    /**
     * @return void
     */
    public function testItProducesABodyStartingWithItsBoundary(): void
    {
        $request = (new MultipartRequestStub())
            ->setDescription('x');

        $body = (string) $this->factory->create($request);

        self::assertSame(0, strpos($body, '--'), 'multipart body must start with --<boundary>');
        self::assertStringEndsWith("--\r\n", $body);
    }

    /**
     * @return void
     */
    public function testItThrowsWhenNoStreamFactoryIsWired(): void
    {
        $factory = new MultipartStreamFactory(
            null,
            $this->normalizerStub(),
            PropertyAccess::createPropertyAccessor(),
            new CamelCaseToSnakeCaseNameConverter(null, false)
        );

        $this->expectException(LogicException::class);

        $factory->create((new MultipartRequestStub())->setDescription('x'));
    }

    /**
     * Stand-in for the request serializer: returns a deep-normalized representation
     * (scalars/arrays as-is, dates formatted, objects turned into arrays) so the
     * factory has something to flatten.
     *
     * @return NormalizerInterface
     */
    private function normalizerStub(): NormalizerInterface
    {
        $normalize = static function ($value) use (&$normalize) {
            if (null === $value || is_scalar($value)) {
                return $value;
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if ($value instanceof NestedObjectStub) {
                return ['label' => $value->getLabel(), 'code' => $value->getCode()];
            }

            if (is_iterable($value)) {
                $normalized = [];
                foreach ($value as $key => $item) {
                    $normalized[$key] = $normalize($item);
                }

                return $normalized;
            }

            return (string) $value;
        };

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('normalize')->willReturnCallback(
            static function ($value) use ($normalize) {
                return $normalize($value);
            }
        );

        return $normalizer;
    }
}
