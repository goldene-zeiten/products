<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns the option selections of a basket line - the raw values a customer submitted with "add to basket",
 * keyed by option identifier - into the options the line is sold with. Dispatched for every line, with or
 * without selections, when it is added and every time the basket is resolved, so required options are
 * enforced and labels and surcharges always follow the current definitions.
 *
 * A listener adds one {@see LineItemOption} per selection it accepts - its `value` is stored as the
 * selection, so resolving the stored value must return the same `value` - or rejects the selections with a
 * message the customer can read. Selections nobody resolves are not stored; a stored line whose selections
 * no longer all resolve leaves the basket. Without a listener a line has no options.
 */
final class ResolveLineItemOptionsEvent
{
    /**
     * @var LineItemOption[]
     */
    private array $options = [];

    private ?string $rejectionReason = null;

    /**
     * @param array<string, string> $selections
     */
    public function __construct(
        private readonly Product $product,
        private readonly ?Article $article,
        private readonly array $selections,
        private readonly ServerRequestInterface $request
    ) {}

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    /**
     * @return array<string, string>
     */
    public function getSelections(): array
    {
        return $this->selections;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function addOption(LineItemOption $option): void
    {
        $this->options[] = $option;
    }

    /**
     * @return LineItemOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function reject(string $reason): void
    {
        $this->rejectionReason = $reason;
    }

    public function isRejected(): bool
    {
        return $this->rejectionReason !== null;
    }

    public function getRejectionReason(): string
    {
        return $this->rejectionReason ?? '';
    }
}
