<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Pricing;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\PriceHistory\PriceHistoryLookbackService;
use GoldeneZeiten\Products\ViewHelpers\Format\RenderingContextRequestResolverInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class ReferencePriceViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private readonly PriceHistoryLookbackService $lookbackService,
        private readonly RenderingContextRequestResolverInterface $requestResolver,
        private readonly RenderingContextVariableScopeInterface $variableScope,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('product', 'mixed', 'The product entity', false, null);
        $this->registerArgument('article', 'mixed', 'The article entity', false, null);
        $this->registerArgument('currentPrice', 'mixed', 'The current price (Money)', true);
        $this->registerArgument('as', 'string', 'Variable name to store the result in', false, 'referencePrice');
        $this->registerArgument('lookbackDays', 'int', 'Override the lookback days setting', false, null);
    }

    public function render(): string
    {
        $product = $this->arguments['product'];
        $article = $this->arguments['article'];
        $currentPrice = $this->arguments['currentPrice'];
        $as = (string)$this->arguments['as'];
        $lookbackDaysOverride = $this->arguments['lookbackDays'];

        // Get product/article uids
        $productUid = null;
        $articleUid = null;

        if (is_object($product) && method_exists($product, 'getUid')) {
            $productUid = (int)$product->getUid();
        }

        if (is_object($article) && method_exists($article, 'getUid')) {
            $articleUid = (int)$article->getUid();
        }

        if (($productUid === null || $productUid === 0) && ($articleUid === null || $articleUid === 0)) {
            return '';
        }

        // Convert currentPrice to Money if needed
        if (!$currentPrice instanceof Money) {
            return '';
        }

        // Resolve lookback days
        $lookbackDays = $lookbackDaysOverride;
        if ($lookbackDays === null) {
            $request = $this->requestResolver->resolveRequest($this->renderingContext);
            if ($request instanceof ServerRequestInterface) {
                $site = $request->getAttribute('site');
                if ($site instanceof Site) {
                    $lookbackDays = (int)$site->getSettings()->get('products.pricing.priceReductionLookbackDays', 30);
                }
            }
            if ($lookbackDays === null) {
                $lookbackDays = 30;
            }
        }

        // Calculate lookback timestamp
        $sinceTimestamp = time() - ($lookbackDays * 86400);

        // Find lowest price
        $lowestPrice = $this->lookbackService->findLowestPriceSince($productUid, $articleUid, $sinceTimestamp);

        // Don't render if no price found or if found price is >= current price
        if ($lowestPrice === null || $lowestPrice->getCents() >= $currentPrice->getCents()) {
            return '';
        }

        // Add the reference price to the template context.
        $this->variableScope->setVariable($this->renderingContext, $as, $lowestPrice);

        $output = $this->renderChildren();

        // Clean up the variable
        $this->variableScope->removeVariable($this->renderingContext, $as);

        return $output;
    }
}
