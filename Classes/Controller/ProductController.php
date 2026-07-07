<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Model\AttributeValue;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

final class ProductController extends ActionController
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}

    public function listAction(): ResponseInterface
    {
        $products = $this->productRepository->findAll();
        $this->view->assign('products', $products);
        return $this->htmlResponse();
    }

    public function listByAjaxAction(): ResponseInterface
    {
        $products = $this->productRepository->findAll();
        $this->view->assign('products', $products);
        return $this->htmlResponse();
    }

    public function showAction(Product $product): ResponseInterface
    {
        $this->view->assignMultiple([
            'product' => $product,
            'variantAttributes' => $product->getVariantAttributes(),
            'variantMap' => $this->buildVariantMap($product),
        ]);
        return $this->htmlResponse();
    }

    /**
     * JSON map of "sorted attribute-value uids joined by comma" -> article uid, read by the
     * variant selector JS to resolve a combination client-side without a server round-trip.
     */
    private function buildVariantMap(Product $product): string
    {
        $map = [];
        foreach ($product->getArticles() as $article) {
            $valueUids = $this->attributeValueUids($article->getAttributeValues());
            if ($valueUids !== []) {
                $map[implode(',', $valueUids)] = $article->getUid();
            }
        }
        return json_encode($map, JSON_THROW_ON_ERROR);
    }

    /**
     * @param ObjectStorage<AttributeValue> $attributeValues
     * @return int[]
     */
    private function attributeValueUids(ObjectStorage $attributeValues): array
    {
        $uids = [];
        foreach ($attributeValues as $value) {
            if ($value->getUid() !== null) {
                $uids[] = $value->getUid();
            }
        }
        sort($uids);
        return $uids;
    }
}
