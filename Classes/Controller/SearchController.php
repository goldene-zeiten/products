<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Service\Search\FacetedBrowseService;
use GoldeneZeiten\Products\Service\Search\SearchService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class SearchController extends ActionController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly FacetedBrowseService $facetedBrowseService
    ) {}

    public function searchAction(string $term = '', ?int $category = null, int $page = 1): ResponseInterface
    {
        $browseMode = $this->request->getAttribute('currentContentObject')?->data['tx_products_search_browse_mode'] ?? 'text';

        if ($browseMode === 'text' || !in_array($browseMode, ['firstletter', 'year', 'field', 'lastentries'], true)) {
            $this->view->assign('result', $this->searchService->search($term, $category, $page, $this->request));
        } else {
            $target = $this->request->getAttribute('currentContentObject')?->data['tx_products_search_target'] ?? 'products';
            $field = $this->request->getAttribute('currentContentObject')?->data['tx_products_search_field'] ?? '';
            $this->view->assign('browseGroups', $this->facetedBrowseService->browse($browseMode, $target, $field));
        }

        return $this->htmlResponse();
    }
}
