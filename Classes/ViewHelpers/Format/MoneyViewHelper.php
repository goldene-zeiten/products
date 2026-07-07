<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Format;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception as ViewHelperException;

final class MoneyViewHelper extends AbstractViewHelper
{
    private const POSITION_AUTO = 'auto';
    private const POSITION_BEFORE = 'before';
    private const POSITION_AFTER = 'after';

    public function __construct(
        private readonly RenderingContextRequestResolverInterface $requestResolver,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'mixed', 'The money object or cents to format', true);
        $this->registerArgument('currency', 'string', 'ISO 4217 currency code', false, 'EUR');
        $this->registerArgument(
            'locale',
            'string',
            'Locale used for formatting, defaults to the current site language locale',
            false
        );
        $this->registerArgument(
            'position',
            'string',
            'Currency symbol position: "auto" (locale-dependent), "before" or "after" the value',
            false,
            self::POSITION_AUTO
        );
    }

    public function render(): string
    {
        $value = $this->arguments['value'];
        $currency = strtoupper((string)$this->arguments['currency']);
        $position = (string)$this->arguments['position'];

        if (!in_array($position, [self::POSITION_AUTO, self::POSITION_BEFORE, self::POSITION_AFTER], true)) {
            throw new ViewHelperException(
                'The "position" argument must be one of "auto", "before" or "after".',
                1751740800
            );
        }

        if (!$value instanceof Money) {
            if (!is_numeric($value)) {
                return '';
            }
            $value = Money::fromCents((int)$value);
        }

        $formatter = new \NumberFormatter($this->resolveLocale(), \NumberFormatter::CURRENCY);

        if ($position === self::POSITION_AUTO) {
            return (string)$formatter->formatCurrency($value->getCents() / 100, $currency);
        }

        $symbol = $this->resolveCurrencySymbol($formatter, $currency);

        return $position === self::POSITION_BEFORE
            ? $symbol . "\u{00A0}" . $value->getDecimalString()
            : $value->getDecimalString() . "\u{00A0}" . $symbol;
    }

    private function resolveLocale(): string
    {
        $locale = $this->arguments['locale'] ?? null;
        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        $request = $this->requestResolver->resolveRequest($this->renderingContext);
        if ($request instanceof ServerRequestInterface) {
            $siteLanguage = $request->getAttribute('language');
            if ($siteLanguage instanceof SiteLanguage) {
                return (string)$siteLanguage->getLocale();
            }
        }

        return 'en_US';
    }

    private function resolveCurrencySymbol(\NumberFormatter $formatter, string $currency): string
    {
        $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency);
        $symbol = (string)$formatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

        return $symbol !== '' ? $symbol : $currency;
    }
}
