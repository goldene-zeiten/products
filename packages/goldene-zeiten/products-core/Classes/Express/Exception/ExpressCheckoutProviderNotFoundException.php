<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express\Exception;

/**
 * Thrown when an express-checkout callback references a provider identifier that is not registered - a
 * stale button, a disabled provider, or a tampered request.
 */
final class ExpressCheckoutProviderNotFoundException extends \RuntimeException {}
