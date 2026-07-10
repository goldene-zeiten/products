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
 * The final pricing-chain step: applies whichever is higher of the category-cascading discount
 * (see CategoryDiscountResolver) or the shopper's FE-usergroup/personal discount (see
 * FrontendUserResolver::getDiscountPercent()) on top of GraduatedPriceProvider's quantity-based
 * price. The two discount sources are never stacked - matching FrontendUserResolver's own
 * "best rate wins" precedent for its own two sub-sources - so a shopper with both a personal
 * discount and a product in a discounted category simply gets whichever rate is bigger.
 */
final class CategoryDiscountPriceProvider implements PriceProviderInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly GraduatedPriceProvider $graduatedPriceProvider,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly CategoryDiscountResolver $categoryDiscountResolver,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money
    {
        $price = $this->graduatedPriceProvider->getUnitPrice($product, $article, $quantity, $request);

        $mode = (string)($this->settings['pricing']['discountFieldMode'] ?? 'maxAcrossTree');
        $discountPercent = max(
            $this->categoryDiscountResolver->getDiscountPercent($product, $mode),
            $request !== null ? $this->frontendUserResolver->getDiscountPercent($request) : 0.0
        );

        return $price->discountByPercent($discountPercent);
    }
}
