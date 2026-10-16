<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Service;

use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;
use GoldeneZeiten\Products\Personalization\Domain\Model\Option;
use GoldeneZeiten\Products\Personalization\Service\Exception\InvalidOptionSelectionException;

/**
 * Checks what a customer selected or entered for one option and turns it into the option of the basket line.
 */
final class OptionResolver
{
    /**
     * Letters of any script, digits, spaces and the punctuation an engraver can cut.
     */
    private const ENGRAVING_PATTERN = '/^[\p{L}\p{N} .,\-&\'!?+]*$/u';

    /**
     * @return LineItemOption|null null when nothing was chosen for an optional option
     * @throws InvalidOptionSelectionException
     */
    public function resolve(Option $option, string $rawValue): ?LineItemOption
    {
        $value = trim($rawValue);
        if ($value === '') {
            if ($option->isRequired()) {
                throw new InvalidOptionSelectionException('required', $option->getTitle(), 1792173830);
            }
            return null;
        }
        return $option->isText() ? $this->resolveText($option, $value) : $this->resolveSelection($option, $value);
    }

    private function resolveText(Option $option, string $value): LineItemOption
    {
        if ($option->getMaxLength() > 0 && mb_strlen($value) > $option->getMaxLength()) {
            throw new InvalidOptionSelectionException('too_long', $option->getTitle(), 1792173831);
        }
        if ($option->getCharacterSet() === Option::CHARACTER_SET_ENGRAVING && preg_match(self::ENGRAVING_PATTERN, $value) !== 1) {
            throw new InvalidOptionSelectionException('invalid_characters', $option->getTitle(), 1792173832);
        }
        return new LineItemOption($option->getIdentifier(), $option->getTitle(), $value, $value, $option->getSurcharge(), $option->isPersonalizing());
    }

    private function resolveSelection(Option $option, string $value): LineItemOption
    {
        $item = $option->findItem($value);
        if ($item === null) {
            throw new InvalidOptionSelectionException('unknown_choice', $option->getTitle(), 1792173833);
        }
        return new LineItemOption(
            $option->getIdentifier(),
            $option->getTitle(),
            $item->getValue(),
            $item->getTitle(),
            $option->getSurcharge()->add($item->getSurcharge()),
            $item->isPersonalizing()
        );
    }
}
