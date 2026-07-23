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

namespace Auto1\ServiceAPIClientBundle\Service\Request\BodyFactory;

use Auto1\ServiceAPIClientBundle\Service\Request\Multipart\MultipartStreamFactoryInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIRequest\ServiceRequestInterface;

/**
 * Builds a streaming multipart/form-data body. Registered only when a multipart
 * stream factory is available, so it is an opt-in capability rather than a
 * hard requirement of the request factory.
 */
class MultipartRequestBodyFactory implements RequestBodyFactoryInterface
{
    const FORMAT = 'multipart';

    /**
     * @var MultipartStreamFactoryInterface
     */
    private $multipartStreamFactory;

    /**
     * @param MultipartStreamFactoryInterface $multipartStreamFactory
     */
    public function __construct(MultipartStreamFactoryInterface $multipartStreamFactory)
    {
        $this->multipartStreamFactory = $multipartStreamFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ServiceRequestInterface $serviceRequest, EndpointInterface $endpoint): bool
    {
        return self::FORMAT === $endpoint->getRequestFormat();
    }

    /**
     * {@inheritdoc}
     */
    public function create(ServiceRequestInterface $serviceRequest, EndpointInterface $endpoint)
    {
        return $this->multipartStreamFactory->create($serviceRequest);
    }
}
