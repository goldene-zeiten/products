<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Form;

use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class CountryItemsProcFunc
{
    /**
     * @param array<string, mixed> $config
     */
    public function getCountries(array &$config): void
    {
        $countryProvider = GeneralUtility::makeInstance(CountryProvider::class);
        $countries = $countryProvider->getAll();

        $config['items'][] = ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxrate.country.fallback', 'value' => ''];

        foreach ($countries as $country) {
            $config['items'][] = [
                'label' => $country->getName() . ' (' . $country->getAlpha2IsoCode() . ')',
                'value' => $country->getAlpha2IsoCode(),
            ];
        }
    }
}
