<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\LineOptionsFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Event\ResolveLineItemOptionsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Offers an engraving of up to ten characters for a surcharge of 5.00, optionally required, and a gift box
 * for free.
 */
#[AsEventListener]
final class LineItemOptionsListener
{
    public static bool $enabled = false;
    public static bool $engravingRequired = false;

    public function __invoke(ResolveLineItemOptionsEvent $event): void
    {
        if (!self::$enabled) {
            return;
        }
        $selections = $event->getSelections();
        $engraving = trim($selections['engraving'] ?? '');
        if (mb_strlen($engraving) > 10) {
            $event->reject('The engraving is longer than ten characters.');
            return;
        }
        if ($engraving === '' && self::$engravingRequired) {
            $event->reject('Please enter an engraving.');
            return;
        }
        if ($engraving !== '') {
            $event->addOption(new LineItemOption('engraving', 'Engraving', $engraving, $engraving, Money::fromCents(500), true));
        }
        if (($selections['giftBox'] ?? '') === '1') {
            $event->addOption(new LineItemOption('giftBox', 'Gift box', '1', 'Yes', Money::fromCents(0), false));
        }
    }
}
