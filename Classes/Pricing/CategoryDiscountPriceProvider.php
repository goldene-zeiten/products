<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Applies whichever is higher of the category discount ({@see CategoryDiscountResolver}) or the
 * shopper's personal discount ({@see FrontendUserResolver::getDiscountPercent()}); never stacked.
 */
final class CategoryDiscountPriceProvider implements PriceProviderInterface
{
    public function __construct(
        private readonly GraduatedPriceProvider $graduatedPriceProvider,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly CategoryDiscountResolver $categoryDiscountResolver,
        private readonly ConfigurationManagerInterface $configurationManager
    ) {}

    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money
    {
        $price = $this->graduatedPriceProvider->getUnitPrice($product, $article, $quantity, $request);

        if ($request !== null) {
            $this->configurationManager->setRequest($request);
            $settings = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'Products'
            );
            $mode = (string)($settings['pricing']['discountFieldMode'] ?? 'maxAcrossTree');
        } else {
            $mode = 'maxAcrossTree';
        }
        $discountPercent = max(
            $this->categoryDiscountResolver->getDiscountPercent($product, $mode),
            $request !== null ? $this->frontendUserResolver->getDiscountPercent($request) : 0.0
        );

        return $price->discountByPercent($discountPercent);
    }
}
