<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Repository\ManufacturerRepository;
use GoldeneZeiten\Products\Core\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Core\Service\ContentElement\SelectedCategoriesResolver;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class ManufacturerController extends ActionController
{
    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepository,
        private readonly CategoryTreeService $categoryTreeService,
        private readonly SelectedCategoriesResolver $selectedCategoriesResolver,
    ) {}

    /**
     * Lists the manufacturers with a product in any category of the content element and in every one of its
     * filter categories, each including its subcategories.
     */
    public function listAction(): ResponseInterface
    {
        $groups = [$this->subtreeUids($this->selectedCategoriesResolver->resolveUids($this->request))];
        $filterCategories = (string)($this->request->getAttribute('currentContentObject')?->data['tx_products_filter_categories'] ?? '');
        foreach (GeneralUtility::intExplode(',', $filterCategories, true) as $filterCategoryUid) {
            $groups[] = $this->subtreeUids([$filterCategoryUid]);
        }
        $this->view->assign('manufacturers', $this->manufacturerRepository->findWithProductsInCategories($groups));
        return $this->htmlResponse();
    }

    /**
     * @param int[] $categoryUids
     * @return int[]
     */
    private function subtreeUids(array $categoryUids): array
    {
        $uids = [];
        foreach ($categoryUids as $categoryUid) {
            $uids = array_merge($uids, $this->categoryTreeService->getSubtreeUids($categoryUid));
        }
        return array_values(array_unique($uids));
    }
}
