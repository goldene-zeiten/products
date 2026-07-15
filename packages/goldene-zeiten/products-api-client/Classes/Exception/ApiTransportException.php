<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Exception;

/**
 * Thrown when an API request fails below the HTTP layer - a connection reset, DNS failure, timeout - so
 * no response was ever received. A consuming client decides whether that is fatal or a reason to fall
 * back, exactly as it would for a non-2xx response.
 */
final class ApiTransportException extends \RuntimeException {}
