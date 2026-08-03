# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## [Unreleased]

### Breaking

- `Service\Request\RequestFactory::__construct()` argument 2 changed from
  `Symfony\Component\Serializer\SerializerInterface` to
  `RequestBodyFactoryRegistryInterface` (`auto1.api.request.body_factory.registry`).
  Consumers of the `auto1.api.request.factory` service are unaffected; applications
  or bundles that instantiate or re-register `RequestFactory` themselves must update
  the wiring — the mismatch surfaces only at runtime as a `TypeError`, not at
  container compile time.

### Added

- Support for sending `multipart/form-data` requests. Endpoints with
  `requestFormat: multipart` build a streaming PSR-7 body from the request DTO:
  `\Psr\Http\Message\StreamInterface` values become file parts (filename and
  `Content-Type` taken from the stream's `filename` / `mime-type` metadata),
  whether the stream is a top-level property or an element of an array/collection
  (a `files[]` field). Other fields are serialized via the request normalizer —
  so dates and value objects are stringified consistently with the other formats
  — and nested objects/arrays are flattened into `name[child]` field names. A
  stream nested inside an object is not detected as a file part. Requires a PSR-7
  implementation to be installed (its PSR-17 factories are discovered
  automatically).
- `auto1.api.request_body_factory` tagged extension point. Request bodies are
  built by `RequestBodyFactoryInterface` strategies resolved first-match by
  priority; new formats can be added as tagged services.
- Bundle-local `NullLogger` fallback: the bundle now compiles and runs without a
  `logger` service (e.g. without MonologBundle). When a `logger` service exists
  it is used as before.

### Changed

- Request-body construction refactored into a strategy registry.
  `RequestFactory` now depends on `RequestBodyFactoryRegistryInterface` instead
  of the serializer directly (see Breaking above).
- `RequestFactory::create()` no longer sets a request body when the serialized
  body is an empty string (previously an empty stream was attached).
- Applications overriding the `request_visitors` configuration node must add the
  two new multipart entries (`auto1.api.request.visitor.content_type.multipart`
  for format `multipart`, next to the existing visitors) manually — the node
  replaces the defaults rather than merging with them.

### Dependencies

- Added `php-http/multipart-stream-builder` and `symfony/property-access`
  (required to build multipart bodies).
- `auto1-oss/service-api-components-bundle` is required at the version providing
  `EndpointInterface::FORMAT_MULTIPART` and `Multipart\MetadataStream`.
