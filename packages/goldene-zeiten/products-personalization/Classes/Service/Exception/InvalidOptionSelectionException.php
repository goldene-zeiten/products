<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Service\Exception;

/**
 * A customer's selection for an option was refused; the reason key names the message the customer reads.
 */
final class InvalidOptionSelectionException extends \RuntimeException
{
    public function __construct(
        private readonly string $reasonKey,
        private readonly string $optionTitle,
        int $code
    ) {
        parent::__construct(sprintf('The selection for option "%s" is refused: %s.', $optionTitle, $reasonKey), $code);
    }

    public function getReasonKey(): string
    {
        return $this->reasonKey;
    }

    public function getOptionTitle(): string
    {
        return $this->optionTitle;
    }
}
