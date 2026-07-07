<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * A logged-in shopper's saved product. Unlike Order/OrderItem, this is live user-mutable state, not
 * an audit snapshot, so it holds a real relation to Product rather than an informational FK.
 */
#[Exclude]
class WishlistItem extends AbstractEntity
{
    protected int $frontendUser = 0;
    protected ?Product $product = null;
    protected ?\DateTime $created = null;

    public function getFrontendUser(): int
    {
        return $this->frontendUser;
    }

    public function setFrontendUser(int $frontendUser): void
    {
        $this->frontendUser = $frontendUser;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): void
    {
        $this->product = $product;
    }

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    public function setCreated(?\DateTime $created): void
    {
        $this->created = $created;
    }
}
