<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Handoff;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Basket\Exception\LineItemOptionsRejectedException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Adds the lines of an assistant basket to the basket in the customer's frontend session. Every line goes
 * through the shop's basket service again, so what is no longer sellable by the time the customer opens the
 * link (hidden, option refused, over the limits) is left out, and the lines add to what the customer already
 * collected. A basket is merged once per session, so opening the link twice does not double the quantities.
 */
final readonly class FrontendBasketMerger
{
    private const SESSION_KEY = 'tx_products_mcp_merged';

    public function __construct(
        private BasketService $basketService
    ) {}

    public function merge(string $basketToken, Basket $source, ServerRequestInterface $request): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return;
        }
        $merged = $this->mergedBaskets($frontendUser);
        if (in_array($basketToken, $merged, true)) {
            return;
        }
        $this->addLines($source, $request);
        $frontendUser->setKey('ses', self::SESSION_KEY, json_encode([...$merged, $basketToken], JSON_THROW_ON_ERROR));
        $frontendUser->storeSessionData();
    }

    private function addLines(Basket $source, ServerRequestInterface $request): void
    {
        foreach ($source->getItems() as $item) {
            try {
                $this->basketService->add(
                    $request,
                    $item->getProductUid(),
                    $item->getArticleUid(),
                    $item->getQuantity(),
                    $item->getSelections()
                );
            } catch (LineItemOptionsRejectedException) {
                continue;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function mergedBaskets(FrontendUserAuthentication $frontendUser): array
    {
        $stored = json_decode((string)$frontendUser->getKey('ses', self::SESSION_KEY), true);

        return is_array($stored) ? array_values(array_map('strval', $stored)) : [];
    }
}
