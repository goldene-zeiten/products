<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Exception;

/**
 * Thrown when an OAuth 2.0 access token cannot be obtained: the token endpoint refused the credentials,
 * returned a non-200, or sent a body that carried no token. The caller turns this into its own
 * domain outcome (a shipping carrier stays silent, a payment method reports failure).
 */
final class OAuth2AuthenticationException extends \RuntimeException {}
