<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Exception;

/**
 * Thrown by a tool when the call cannot be answered, for example because the product asked for does not
 * exist. The assistant gets the message as a tool error it can read and react to.
 */
final class ToolFailedException extends \RuntimeException {}
