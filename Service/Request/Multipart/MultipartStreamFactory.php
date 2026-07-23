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

namespace Auto1\ServiceAPIClientBundle\Service\Request\Multipart;

use Auto1\ServiceAPIRequest\ServiceRequestInterface;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use LogicException;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionObject;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Builds a streaming multipart/form-data body from a request DTO.
 *
 * `StreamInterface` values become file parts (filename / Content-Type taken from the
 * stream's `filename` / `mime-type` metadata, mirroring the incoming side's
 * UploadedFileStream), including streams nested in a collection such as a `files[]`
 * property, which are emitted as `name[0]`, `name[1]`, ... file parts. Every other
 * property value is run through the request serializer's normalizer, so dates, value
 * objects and nested objects are stringified exactly as they are for the other request
 * formats; nested arrays are flattened into `name[child]` field names.
 *
 * The DTO is read property-by-property rather than normalized as a whole because a
 * live `StreamInterface` cannot survive `Serializer::normalize()` (on Symfony 7 a
 * normalizer may not return an object).
 */
class MultipartStreamFactory implements MultipartStreamFactoryInterface
{
    const FORMAT = 'multipart';
    const DEFAULT_CONTENT_TYPE = 'application/octet-stream';

    /**
     * @var StreamFactoryInterface|null
     */
    private $streamFactory;

    /**
     * @var NormalizerInterface
     */
    private $normalizer;

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var NameConverterInterface
     */
    private $nameConverter;

    /**
     * @param StreamFactoryInterface|null $streamFactory    PSR-17 factory; null when no
     *                                                      PSR-7 implementation is installed.
     * @param NormalizerInterface         $normalizer       Stringifies non-file field values
     *                                                      (dates, value objects, nested objects).
     * @param PropertyAccessorInterface   $propertyAccessor Resolves get/is/has/public-property access.
     * @param NameConverterInterface      $nameConverter    Derives the wire field name from the property name.
     */
    public function __construct(
        ?StreamFactoryInterface $streamFactory,
        NormalizerInterface $normalizer,
        PropertyAccessorInterface $propertyAccessor,
        NameConverterInterface $nameConverter
    ) {
        $this->streamFactory = $streamFactory;
        $this->normalizer = $normalizer;
        $this->propertyAccessor = $propertyAccessor;
        $this->nameConverter = $nameConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function create(ServiceRequestInterface $serviceRequest): StreamInterface
    {
        if (null === $this->streamFactory) {
            throw new LogicException(sprintf(
                'A PSR-17 "%s" must be wired to send multipart/form-data requests. '
                . 'Install a PSR-7 implementation (e.g. guzzlehttp/psr7, nyholm/psr7) '
                . 'and register its stream factory.',
                StreamFactoryInterface::class
            ));
        }

        $builder = new MultipartStreamBuilder($this->streamFactory);

        foreach ($this->readProperties($serviceRequest) as $name => $value) {
            $this->appendValue($builder, $name, $value);
        }

        return $builder->build();
    }

    /**
     * @param MultipartStreamBuilder $builder
     * @param string                 $name
     * @param mixed                  $value
     *
     * @return void
     */
    private function appendValue(MultipartStreamBuilder $builder, string $name, $value): void
    {
        if (null === $value) {
            return;
        }

        if ($value instanceof StreamInterface) {
            $builder->addResource($name, $value, $this->fileOptions($value, $name));

            return;
        }

        // Recurse into collections before normalizing so that StreamInterface elements
        // (e.g. a `files[]` property) become file parts rather than being handed to the
        // serializer, which cannot normalize a live stream.
        if (is_iterable($value)) {
            foreach ($value as $key => $item) {
                $this->appendValue($builder, sprintf('%s[%s]', $name, $key), $item);
            }

            return;
        }

        // Reuse the serializer so dates/value objects/nested objects are stringified
        // the same way as for the other request formats.
        $normalized = $this->normalizer->normalize($value, self::FORMAT);

        $this->appendNormalized($builder, $name, $normalized);
    }

    /**
     * @param MultipartStreamBuilder $builder
     * @param string                 $name
     * @param mixed                  $value Already-normalized scalar or (nested) iterable.
     *
     * @return void
     */
    private function appendNormalized(MultipartStreamBuilder $builder, string $name, $value): void
    {
        if (null === $value) {
            return;
        }

        if (is_iterable($value)) {
            foreach ($value as $key => $item) {
                $this->appendNormalized($builder, sprintf('%s[%s]', $name, $key), $item);
            }

            return;
        }

        $builder->addResource($name, $this->stringify($value));
    }

    /**
     * @param StreamInterface $stream
     * @param string          $name
     *
     * @return array
     */
    private function fileOptions(StreamInterface $stream, string $name): array
    {
        $filename = $stream->getMetadata('filename');
        $mimeType = $stream->getMetadata('mime-type');

        return [
            'filename' => is_string($filename) && '' !== $filename ? $filename : $name,
            'headers' => [
                'Content-Type' => is_string($mimeType) && '' !== $mimeType ? $mimeType : self::DEFAULT_CONTENT_TYPE,
            ],
        ];
    }

    /**
     * Maps readable DTO properties to their values, keyed by the converted (wire) field name.
     *
     * @param ServiceRequestInterface $serviceRequest
     *
     * @return array
     */
    private function readProperties(ServiceRequestInterface $serviceRequest): array
    {
        $fields = [];

        foreach ((new ReflectionObject($serviceRequest))->getProperties() as $property) {
            $name = $property->getName();
            if (!$this->propertyAccessor->isReadable($serviceRequest, $name)) {
                continue;
            }

            $fields[$this->nameConverter->normalize($name)] = $this->propertyAccessor->getValue($serviceRequest, $name);
        }

        return $fields;
    }

    /**
     * @param int|float|string|bool $value
     *
     * @return string
     */
    private function stringify($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
