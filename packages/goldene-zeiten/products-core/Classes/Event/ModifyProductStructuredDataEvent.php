<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Lets integrators extend or replace the schema.org Product data of a product detail page before it is
 * rendered as JSON-LD - add a brand, a GTIN, reviews or shipping details. An empty array renders nothing.
 */
final class ModifyProductStructuredDataEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
        private readonly Product $product,
        private readonly ?Article $article,
        private readonly ServerRequestInterface $request,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
