<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Search\Controller;

use GoldeneZeiten\Products\Search\Service\FacetedBrowseService;
use GoldeneZeiten\Products\Search\Service\SearchService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class SearchController extends ActionController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly FacetedBrowseService $facetedBrowseService
    ) {}

    /**
     * @param string[] $keywords values ticked in the keyfield multi-select
     */
    public function searchAction(string $term = '', ?int $category = null, int $page = 1, array $keywords = []): ResponseInterface
    {
        $browseMode = $this->request->getAttribute('currentContentObject')?->data['tx_products_search_browse_mode'] ?? 'text';
        $target = $this->request->getAttribute('currentContentObject')?->data['tx_products_search_target'] ?? 'products';
        $field = $this->request->getAttribute('currentContentObject')?->data['tx_products_search_field'] ?? '';

        if ($browseMode === 'keyfield') {
            $keywordValues = array_map(static fn(mixed $v): string => (string)$v, $keywords);
            $this->view->assign('keyfieldOptions', $this->facetedBrowseService->keyfieldOptions($target, $field));
            $this->view->assign('keyfieldSelected', $keywordValues);
            $this->view->assign('keyfieldResults', $this->facetedBrowseService->filterByValues($target, $field, $keywordValues));
        } elseif ($browseMode === 'text' || !in_array($browseMode, ['firstletter', 'year', 'field', 'lastentries'], true)) {
            $this->view->assign('result', $this->searchService->search($term, $category, $page, $this->request));
        } else {
            $this->view->assign('browseGroups', $this->facetedBrowseService->browse($browseMode, $target, $field));
        }

        return $this->htmlResponse();
    }
}
