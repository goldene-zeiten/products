<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * The absolute address of a product's detail page, built by the site router like the links of the product
 * list, so it carries the same speaking path.
 */
final readonly class ProductUrlFactory
{
    private const PLUGIN_NAMESPACE = 'tx_productscore_productdetail';

    /**
     * @return string empty while the site has no detail page ("products.pids.detailPage")
     */
    public function create(Product $product, McpContext $context): string
    {
        $detailPage = (int)$context->site->getSettings()->get('products.pids.detailPage', 0);
        if ($detailPage === 0) {
            return '';
        }
        $url = $context->site->getRouter()->generateUri($detailPage, [
            self::PLUGIN_NAMESPACE => [
                'controller' => 'Product',
                'action' => 'show',
                'product' => $product->getUid(),
            ],
        ]);
        if ($url->getHost() === '') {
            $requestUri = $context->request->getUri();
            $url = $url->withScheme($requestUri->getScheme())->withHost($requestUri->getHost())->withPort($requestUri->getPort());
        }

        return (string)$url;
    }
}
