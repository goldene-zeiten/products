<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Catalog;

use GoldeneZeiten\Products\Configuration\CreditPointsConfigurationFactory;
use GoldeneZeiten\Products\Domain\Dto\Catalog\ProductListContext;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsBalanceService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * "Products you can afford with your points" - the credit-points programme's own listing. It ships with
 * the extension, but as a registered list mode rather than a branch in the product controller, so it can
 * be placed like any other listing and moves out with credit points later.
 */
final class AffordableProductListModeProvider implements ProductListModeProviderInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CreditPointsBalanceService $creditPointsBalanceService,
        private readonly CreditPointsConfigurationFactory $creditPointsConfigurationFactory,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function getMode(): string
    {
        return 'affordable';
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('tt_content.tx_products_list_mode.affordable', 'Products');
    }

    /**
     * @return \GoldeneZeiten\Products\Domain\Model\Product[]
     */
    public function findProducts(ProductListContext $context): array
    {
        if (!$this->creditPointsConfigurationFactory->create($context->getRequest())->isEnabled()) {
            return [];
        }
        $balance = $this->creditPointsBalanceService->getBalance($this->frontendUserResolver->getUid($context->getRequest()));

        return $this->productRepository->findAffordable($balance);
    }
}
