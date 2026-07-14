<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\ContentElement;

use Psr\Http\Message\ServerRequestInterface;

final class SelectedCategoriesResolver
{
    /**
     * Resolves the current content element's "tx_products_category" field to category uids.
     *
     * @return int[]
     */
    public function resolveUids(ServerRequestInterface $request): array
    {
        $contentObject = $request->getAttribute('currentContentObject');
        $data = $contentObject?->data;
        if (!is_array($data)) {
            return [];
        }
        $rawValue = (string)($data['tx_products_category'] ?? '');
        if ($rawValue === '') {
            return [];
        }
        $uids = array_map('intval', explode(',', $rawValue));
        return array_values(array_filter($uids, static fn(int $uid): bool => $uid > 0));
    }
}
