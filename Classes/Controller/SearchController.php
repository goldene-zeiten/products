<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Service\Search\SearchService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class SearchController extends ActionController
{
    public function __construct(
        private readonly SearchService $searchService
    ) {}

    public function searchAction(string $term = '', ?int $category = null, int $page = 1): ResponseInterface
    {
        $this->view->assign('result', $this->searchService->search($term, $category, $page, $this->request));
        return $this->htmlResponse();
    }
}
