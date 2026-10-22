<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\ViewHelpers\Format;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the name of a country in the language of the current page, e.g. `<p:format.countryName
 * isoCode="IT" />` renders "Italien" on a German page. An ISO code the core does not know is rendered as it
 * is.
 */
final class CountryNameViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly CountryProvider $countryProvider,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly RenderingContextRequestResolverInterface $requestResolver,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('isoCode', 'string', 'The ISO 3166-1 alpha-2 code of the country', true);
    }

    public function render(): string
    {
        $isoCode = (string)$this->arguments['isoCode'];
        $country = $this->countryProvider->getByAlpha2IsoCode($isoCode);
        if ($country === null) {
            return $isoCode;
        }

        $request = $this->requestResolver->resolveRequest($this->renderingContext);
        $siteLanguage = $request instanceof ServerRequestInterface ? $request->getAttribute('language') : null;
        if (!$siteLanguage instanceof SiteLanguage) {
            return $country->getName();
        }

        $name = $this->languageServiceFactory->createFromSiteLanguage($siteLanguage)->sL($country->getLocalizedNameLabel());
        return $name !== '' ? $name : $country->getName();
    }
}
